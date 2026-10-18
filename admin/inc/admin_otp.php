<?php
// ============================================================
// admin/inc/admin_otp.php
// ------------------------------------------------------------
// WhatsApp OTP lifecycle for Super Admin second-factor login.
//
// Responsibilities:
//   - generate a numeric OTP, store only its HASH
//   - deliver it through the WhatsApp provider abstraction
//   - enforce expiry, attempt limits and resend protection
//   - verify submitted codes in constant time
//
// Depends on: admin_otp, admin_users, admin_login_attempts
// ============================================================

require_once __DIR__ . '/whatsapp/WhatsAppFactory.php';

if (!class_exists('AdminOtpService')) {

    class AdminOtpService
    {
        private PDO $pdo;

        /** OTP validity window in seconds. */
        public const OTP_TTL = 300;          // 5 minutes

        /** Maximum verification attempts per OTP. */
        public const MAX_ATTEMPTS = 5;

        /** Minimum seconds between resends. */
        public const RESEND_COOLDOWN = 45;

        /** Maximum resends allowed per OTP window. */
        public const MAX_RESENDS = 3;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        /**
         * Issue and deliver a fresh OTP for an admin.
         *
         * @param array<string,mixed> $admin admin_users row (must include id, phone/full_name)
         * @param string              $purpose e.g. 'login'
         * @return array{ok:bool,message:string,provider?:string}
         */
        public function issue(array $admin, string $purpose = 'login'): array
        {
            $adminId = (int) $admin['id'];

            // Destination: WhatsApp phone number.
            $destination = trim((string) ($admin['phone'] ?? ''));
            if ($destination === '') {
                return [
                    'ok'      => false,
                    'message' => 'No WhatsApp number is linked to this Super Admin account. Contact the system owner.',
                ];
            }
            $digits = preg_replace('/\D+/', '', $destination);
            if (strlen($digits) === 10) {
                $digits = '91' . $digits;
            }
            if (!preg_match('/^[1-9][0-9]{9,14}$/', $digits)) {
                return ['ok' => false, 'message' => 'A valid WhatsApp number is required for this account.'];
            }
            $destination = '+' . $digits;

            // Resend protection: block rapid re-issues.
            $last = $this->latestRecent($adminId, $purpose);
            if ($last && strtotime($last['created_at']) > time() - self::RESEND_COOLDOWN) {
                $wait = self::RESEND_COOLDOWN - (time() - strtotime($last['created_at']));
                return [
                    'ok'      => false,
                    'message' => "Please wait {$wait}s before requesting a new code.",
                ];
            }
            if ($last && (int) $last['resend_count'] >= self::MAX_RESENDS) {
                return [
                    'ok'      => false,
                    'message' => 'Too many codes requested. Please try again later.',
                ];
            }

            // Invalidate previous unconsumed OTPs for this purpose.
            $this->pdo->prepare(
                "UPDATE admin_otp SET is_used = 1
                 WHERE admin_id = ? AND purpose = ? AND is_used = 0"
            )->execute([$adminId, $purpose]);

            $otp = $this->generateCode();

            $resendCount = $last ? ((int) $last['resend_count'] + 1) : 0;

            $stmt = $this->pdo->prepare(
                "INSERT INTO admin_otp
                    (admin_id, otp_hash, purpose, channel, destination, attempts, max_attempts, resend_count, is_used, expires_at)
                 VALUES (:aid, :hash, :purpose, 'whatsapp', :dest, 0, :max, :resend, 0, DATE_ADD(NOW(), INTERVAL :ttl SECOND))"
            );
            $stmt->execute([
                'aid'     => $adminId,
                'hash'    => password_hash($otp, PASSWORD_DEFAULT),
                'purpose' => $purpose,
                'dest'    => $destination,
                'max'     => self::MAX_ATTEMPTS,
                'resend'  => $resendCount,
                'ttl'     => self::OTP_TTL,
            ]);
            $otpId = (int)$this->pdo->lastInsertId();

            // OTPs must use an approved authentication template.
            $provider = null;
            try {
                $provider = WhatsAppFactory::make($this->pdo);
                $sent = $provider->sendOtp($destination, $otp);
            } catch (Throwable $e) {
                $sent = false;
                error_log('[Admin OTP] WhatsApp provider failed; message details redacted.');
            }

            if (!$sent) {
                $this->markUsed($otpId);
                return [
                    'ok'      => false,
                    'message' => 'WhatsApp OTP delivery is not available. Use the configured recovery path or contact the system owner.',
                ];
            }

            $result = [
                'ok'       => true,
                'message'  => 'A verification code has been sent to your WhatsApp.',
                'provider' => $provider?->name() ?? 'unconfigured',
            ];

            return $result;
        }

        /**
         * Verify a submitted code.
         *
         * @return array{ok:bool,message:string}
         */
        public function verify(int $adminId, string $code, string $purpose = 'login'): array
        {
            $code = preg_replace('/\D/', '', $code);

            $row = $this->latestActive($adminId, $purpose);
            if (!$row) {
                return ['ok' => false, 'message' => 'No active code found. Please sign in again.'];
            }

            if (strtotime($row['expires_at']) < time()) {
                $this->markUsed((int) $row['id']);
                return ['ok' => false, 'message' => 'This code has expired. Please request a new one.'];
            }

            if ((int) $row['attempts'] >= (int) $row['max_attempts']) {
                $this->markUsed((int) $row['id']);
                return ['ok' => false, 'message' => 'Too many incorrect attempts. Please sign in again.'];
            }

            if (!password_verify($code, $row['otp_hash'])) {
                $this->pdo->prepare(
                    "UPDATE admin_otp SET attempts = attempts + 1 WHERE id = ?"
                )->execute([(int) $row['id']]);
                $remaining = (int) $row['max_attempts'] - ((int) $row['attempts'] + 1);
                return [
                    'ok'      => false,
                    'message' => 'Incorrect code. ' . max(0, $remaining) . ' attempt(s) remaining.',
                ];
            }

            $this->markUsed((int) $row['id']);
            return ['ok' => true, 'message' => 'verified'];
        }

        /**
         * Whether the admin currently has a pending, unexpired OTP.
         */
        public function hasPending(int $adminId, string $purpose = 'login'): bool
        {
            $row = $this->latestActive($adminId, $purpose);
            return $row && strtotime($row['expires_at']) >= time();
        }

        /* ------------------------------------------------- */

        private function generateCode(): string
        {
            // 6-digit numeric, cryptographically secure.
            return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        }

        /**
         * @return array<string,mixed>|null
         */
        private function latestActive(int $adminId, string $purpose): ?array
        {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM admin_otp
                 WHERE admin_id = ? AND purpose = ? AND is_used = 0
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$adminId, $purpose]);
            $row = $stmt->fetch();
            return $row ?: null;
        }

        /** Include failed deliveries in the resend cooldown/count window. */
        private function latestRecent(int $adminId, string $purpose): ?array
        {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM admin_otp
                 WHERE admin_id = ? AND purpose = ?
                   AND created_at >= DATE_SUB(NOW(), INTERVAL " . self::OTP_TTL . " SECOND)
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute([$adminId, $purpose]);
            $row = $stmt->fetch();
            return $row ?: null;
        }

        private function markUsed(int $otpId): void
        {
            $this->pdo->prepare("UPDATE admin_otp SET is_used = 1 WHERE id = ?")
                ->execute([$otpId]);
        }

    }
}
