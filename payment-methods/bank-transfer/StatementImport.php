<?php

declare(strict_types=1);

namespace KsrPay;

/**
 * StatementImport — Parses a bank CSV statement and inserts credits
 *                   into payment_bank_credits for reconciliation.
 *
 * IMPORTANT RULES (enforced here):
 *  - Only CREDIT direction rows are imported (never DEBIT).
 *  - Duplicate bank_ref rows are silently skipped (UNIQUE constraint).
 *  - No automatic matching happens here — matching is done by BankMatching.
 *  - Every import creates an audit log entry.
 *
 * Expected CSV columns (SBI format, adjust as needed):
 *   Txn Date, Value Date, Description, Ref No./Cheque No., Debit, Credit, Balance
 */
class StatementImport
{
    private const MAX_FILE_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB
    private const SUPPORTED_HEADERS   = [
        // Column header → internal key mapping (lowercase comparison)
        'txn date'          => 'credit_date',
        'value date'        => 'credit_date_alt',
        'description'       => 'narration',
        'ref no./cheque no.' => 'bank_ref',
        'ref no'            => 'bank_ref',
        'reference'         => 'bank_ref',
        'debit'             => 'debit',
        'credit'            => 'credit',
        'balance'           => 'balance',
        'utr'               => 'utr',
    ];

    private AuditLogger $audit;

    public function __construct(private readonly \PDO $pdo)
    {
        $this->audit = new AuditLogger($pdo);
    }

    /**
     * Import a bank CSV file.
     *
     * @param string $filePath    Absolute path to the CSV file (must be in private storage)
     * @param int    $importedBy  Admin user ID performing the import
     * @return array{
     *   imported: int,
     *   duplicates: int,
     *   skipped_debits: int,
     *   skipped_errors: int,
     *   total_rows: int,
     *   errors: string[]
     * }
     */
    public function importCsv(string $filePath, int $importedBy): array
    {
        $result = [
            'imported'       => 0,
            'duplicates'     => 0,
            'skipped_debits' => 0,
            'skipped_errors' => 0,
            'total_rows'     => 0,
            'errors'         => [],
        ];

        // ── Validate file ─────────────────────────────────────────────
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \InvalidArgumentException("Statement file not found or not readable: {$filePath}");
        }
        if (filesize($filePath) > self::MAX_FILE_SIZE_BYTES) {
            throw new \InvalidArgumentException('Statement file exceeds 5 MB limit.');
        }

        // ── Parse CSV ─────────────────────────────────────────────────
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException("Unable to open statement file.");
        }

        $headers      = null;
        $columnMap    = [];
        $insertPrefix = ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite')
            ? 'INSERT OR IGNORE INTO'
            : 'INSERT IGNORE INTO';
        $batchInsert  = $this->pdo->prepare(
            "{$insertPrefix} payment_bank_credits
                (credit_date, bank_ref, utr, narration, amount_paise, imported_by)
             VALUES
                (:credit_date, :bank_ref, :utr, :narration, :amount_paise, :imported_by)"
        );

        try {
            while (($row = fgetcsv($handle)) !== false) {
                // Skip completely blank lines
                if ($row === [null] || (count($row) === 1 && trim((string)$row[0]) === '')) {
                    continue;
                }

                // Header row
                if ($headers === null) {
                    $row[0]    = preg_replace('/^\xEF\xBB\xBF/', '', (string)($row[0] ?? ''));
                    $headers   = array_map('trim', $row);
                    $columnMap = $this->buildColumnMap($headers);
                    if (!isset($columnMap['credit']) || !isset($columnMap['bank_ref'])) {
                        throw new \RuntimeException(
                            'CSV missing required columns "Credit" and/or "Ref No.". ' .
                                'Please export a standard SBI statement CSV.'
                        );
                    }
                    continue;
                }

                $result['total_rows']++;
                $data = array_combine($headers, array_slice(array_pad($row, count($headers), ''), 0, count($headers)));

                // ── Extract values ────────────────────────────────────
                $creditRaw = trim((string)($data[$columnMap['credit']] ?? ''));
                $debitRaw  = isset($columnMap['debit']) ? trim((string)($data[$columnMap['debit']] ?? '')) : '';
                $bankRef   = trim((string)($data[$columnMap['bank_ref']] ?? ''));
                $narration = isset($columnMap['narration']) ? trim((string)($data[$columnMap['narration']] ?? '')) : '';
                $dateKey   = $columnMap['credit_date'] ?? ($columnMap['credit_date_alt'] ?? null);
                $dateRaw   = $dateKey !== null ? trim((string)($data[$dateKey] ?? '')) : '';
                $utr       = isset($columnMap['utr']) ? trim((string)($data[$columnMap['utr']] ?? '')) : '';

                // Skip DEBIT rows — only import CREDITS
                $creditNum = $this->parseAmount($creditRaw);
                $debitNum  = $this->parseAmount($debitRaw);

                if ($creditNum <= 0 && $debitNum > 0) {
                    $result['skipped_debits']++;
                    continue;
                }
                if ($creditNum <= 0) {
                    // Zero or empty credit — skip
                    $result['skipped_debits']++;
                    continue;
                }

                // Parse date
                $creditDate = $this->parseDate($dateRaw);
                if ($creditDate === null) {
                    $result['skipped_errors']++;
                    $result['errors'][] = "Row {$result['total_rows']}: unparseable date '{$dateRaw}'.";
                    continue;
                }

                if (empty($bankRef)) {
                    $result['skipped_errors']++;
                    $result['errors'][] = "Row {$result['total_rows']}: empty bank reference.";
                    continue;
                }

                // ── Insert (IGNORE duplicates via UNIQUE constraint) ──
                $amountPaise = (int)round($creditNum * 100);
                $batchInsert->execute([
                    ':credit_date'  => $creditDate,
                    ':bank_ref'     => $bankRef,
                    ':utr'          => $utr ?: null,
                    ':narration'    => substr($narration, 0, 500),
                    ':amount_paise' => $amountPaise,
                    ':imported_by'  => $importedBy,
                ]);

                $affected = $batchInsert->rowCount();
                if ($affected > 0) {
                    $result['imported']++;
                } else {
                    $result['duplicates']++;
                }
            }
        } finally {
            fclose($handle);
        }

        // Audit log
        $this->audit->log(
            eventType: 'STATEMENT_IMPORT',
            actorType: 'admin',
            actorId: $importedBy,
            notes: "File: " . basename($filePath) .
                " | Imported: {$result['imported']}" .
                " | Duplicates: {$result['duplicates']}" .
                " | Debits skipped: {$result['skipped_debits']}" .
                " | Errors: {$result['skipped_errors']}"
        );

        return $result;
    }

    // ── Private helpers ───────────────────────────────────────────────

    private function buildColumnMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            $key = strtolower(trim($header));
            foreach (self::SUPPORTED_HEADERS as $pattern => $field) {
                if ($key === $pattern || str_contains($key, $pattern)) {
                    if (!isset($map[$field])) {
                        $map[$field] = $header; // original casing for array lookup
                    }
                }
            }
        }
        return $map;
    }

    private function parseAmount(string $raw): float
    {
        $cleaned = preg_replace('/[^0-9.]/', '', $raw);
        return $cleaned === '' ? 0.0 : (float)$cleaned;
    }

    private function parseDate(string $raw): ?string
    {
        if (empty($raw)) {
            return null;
        }

        // Try common formats: d/m/Y, d-m-Y, Y-m-d, d M Y
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd M Y', 'd M y'] as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, trim($raw));
            if ($dt !== false) {
                return $dt->format('Y-m-d');
            }
        }

        // Last attempt: PHP strtotime
        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }

        return null;
    }
}
