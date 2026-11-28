<?php

declare(strict_types=1);

namespace KsrPay;

/**
 * ReceiptGenerator — Generates customer payment receipts.
 *
 * Usage:
 *   $gen = new ReceiptGenerator($pdo);
 *   $html = $gen->generateHtml($paymentId, $userId);
 *   $gen->outputPdf($paymentId, $userId); // sends PDF headers and exits
 *
 * Note: PDF output requires a PHP PDF library.
 * Recommended: TCPDF or mPDF (install via composer).
 * Until a PDF library is available, HTML receipts are served.
 */
class ReceiptGenerator
{
  private const MERCHANT_NAME    = 'KSR GURU JI PAY';
  private const MERCHANT_EMAIL   = 'ksrguruji26@gmail.com';
  private const MERCHANT_PHONE   = '+91-8088584421';
  private const MERCHANT_ADDRESS = 'KSR GURU JI, Hubballi, Karnataka';

  public function __construct(private readonly \PDO $pdo) {}

  /**
   * Load and validate payment + order for the given user.
   * Returns null if not found or access denied.
   */
  public function loadPayment(string $paymentId, int $userId): ?array
  {
    $stmt = $this->pdo->prepare(
      "SELECT
                pt.*,
                o.order_number,
                o.created_at  AS order_date,
                o.name        AS customer_name,
                o.email       AS customer_email,
                o.phone       AS customer_phone,
                o.address,
                o.city,
                o.state,
                o.pincode
             FROM payment_transactions pt
             JOIN orders o ON o.id = pt.order_id
             WHERE pt.payment_id = ?
               AND (pt.user_id = ? OR o.user_id = ?)
               AND pt.status   IN ('success', 'cod_collected')
             LIMIT 1"
    );
    $stmt->execute([$paymentId, $userId, $userId]);
    $payment = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (!$payment) {
      return null;
    }

    // Load order items
    $items = $this->pdo->prepare(
      "SELECT oi.quantity, oi.price, oi.total,
              COALESCE(NULLIF(oi.product_name, ''), p.name, 'Item') AS product_name
             FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = ?
             ORDER BY oi.id"
    );
    $items->execute([(int)$payment['order_id']]);
    $payment['items'] = $items->fetchAll(\PDO::FETCH_ASSOC);

    return $payment;
  }

  /**
   * Generate HTML receipt string.
   */
  public function generateHtml(string $paymentId, int $userId): ?string
  {
    $p = $this->loadPayment($paymentId, $userId);
    if (!$p) {
      return null;
    }

    $amountINR   = number_format((int)$p['amount_paise'] / 100, 2);
    $receiptNo   = 'RCP-' . strtoupper(substr($paymentId, 4, 12));
    $paidAt      = $p['verified_at'] ?? $p['updated_at'];
    $paidAtFormatted = $paidAt ? date('d M Y, h:i A', strtotime($paidAt)) : '—';
    $method      = strtoupper(str_replace('_', ' ', $p['method']));
    $merchantEmail = self::MERCHANT_EMAIL;
    $merchantPhone = self::MERCHANT_PHONE;
    $merchantAddress = self::MERCHANT_ADDRESS;
    $generatedAt = date('d M Y, h:i A');

    $itemRows = '';
    foreach ((array)($p['items'] ?? []) as $item) {
      $itemRows .= '<tr>'
        . '<td>' . htmlspecialchars($item['product_name']) . '</td>'
        . '<td style="text-align:center;">' . (int)$item['quantity'] . '</td>'
        . '<td style="text-align:right;">₹' . number_format((float)$item['price'], 2) . '</td>'
        . '<td style="text-align:right;">₹' . number_format((float)$item['total'], 2) . '</td>'
        . '</tr>';
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt {$receiptNo}</title>
<style>
  body { font-family: Arial, sans-serif; font-size: 13px; color: #222; margin: 0; padding: 20px; }
  .receipt { max-width: 640px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; padding: 24px; }
  .header { text-align: center; border-bottom: 2px solid #1a7f4b; padding-bottom: 16px; margin-bottom: 20px; }
  .header h1 { color: #1a7f4b; margin: 0 0 4px; font-size: 22px; }
  .header p { margin: 2px 0; color: #555; font-size: 12px; }
  .badge { display: inline-block; background: #1a7f4b; color: #fff; border-radius: 4px; padding: 3px 10px; font-size: 12px; font-weight: bold; }
  .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 24px; margin-bottom: 20px; }
  .meta .label { color: #888; font-size: 11px; text-transform: uppercase; }
  .meta .value { font-weight: bold; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
  th { background: #f5f5f5; padding: 8px; text-align: left; font-size: 12px; }
  td { padding: 7px 8px; border-bottom: 1px solid #f0f0f0; }
  .total-row td { font-weight: bold; font-size: 15px; border-top: 2px solid #1a7f4b; }
  .footer { text-align: center; font-size: 11px; color: #aaa; margin-top: 20px; }
</style>
</head>
<body>
<div class="receipt">
  <div class="header">
    <h1>KSR GURU JI PAY</h1>
    <p>Payment Receipt</p>
    <p>Secure Payments · Clear Records · Trusted Transactions</p>
  </div>

  <div style="text-align:center;margin-bottom:20px;">
    <span class="badge">✔ PAYMENT CONFIRMED</span>
  </div>

  <div class="meta">
    <div><div class="label">Receipt No.</div><div class="value">{$receiptNo}</div></div>
    <div><div class="label">Payment Date</div><div class="value">{$paidAtFormatted}</div></div>
    <div><div class="label">Order ID</div><div class="value">{$p['order_number']}</div></div>
    <div><div class="label">Payment ID</div><div class="value">{$paymentId}</div></div>
    <div><div class="label">Payment Method</div><div class="value">{$method}</div></div>
    <div><div class="label">Bank / Provider Ref</div><div class="value">{$p['bank_confirmed_ref']}</div></div>
    <div><div class="label">Customer</div><div class="value">{$p['customer_name']}</div></div>
    <div><div class="label">Phone</div><div class="value">{$p['customer_phone']}</div></div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Item</th>
        <th style="text-align:center;">Qty</th>
        <th style="text-align:right;">Unit Price</th>
        <th style="text-align:right;">Total</th>
      </tr>
    </thead>
    <tbody>
      {$itemRows}
    </tbody>
    <tfoot>
      <tr class="total-row">
        <td colspan="3">Total Paid</td>
        <td style="text-align:right;">₹{$amountINR}</td>
      </tr>
    </tfoot>
  </table>

  <p style="font-size:12px;color:#555;">
    Merchant: <strong>KSR GURU JI PAY</strong><br>
    Contact: {$merchantEmail} · {$merchantPhone}<br>
    {$merchantAddress}
  </p>

  <div class="footer">
    This is a computer-generated receipt and does not require a physical signature.<br>
    Generated: {$generatedAt}
  </div>
</div>
</body>
</html>
HTML;
  }

  /**
   * Output HTML receipt directly (for browser download).
   * In a future release, replace with PDF output via TCPDF/mPDF.
   */
  public function outputHtmlReceipt(string $paymentId, int $userId): void
  {
    $html = $this->generateHtml($paymentId, $userId);
    if ($html === null) {
      http_response_code(404);
      echo 'Receipt not found or payment not yet confirmed.';
      return;
    }

    $receiptNo = 'RCP-' . strtoupper(substr($paymentId, 4, 12));
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="Receipt-' . $receiptNo . '.html"');
    echo $html;
  }

  /**
   * Store receipt HTML to private storage.
   * Returns the stored file path, or null on failure.
   */
  public function storeReceipt(string $paymentId, int $userId, string $storageDir): ?string
  {
    $html = $this->generateHtml($paymentId, $userId);
    if ($html === null) {
      return null;
    }

    if (!is_dir($storageDir)) {
      @mkdir($storageDir, 0750, true);
      // Protect from direct web access
      @file_put_contents($storageDir . '/.htaccess', "Deny from all\n");
    }

    $receiptNo = 'RCP-' . strtoupper(substr($paymentId, 4, 12));
    $filename  = $storageDir . '/' . $receiptNo . '.html';

    if (file_put_contents($filename, $html) !== false) {
      return $filename;
    }

    return null;
  }
}
