<?php
// ============================================================
// admin/inc/whatsapp/WhatsAppFactory.php
// ------------------------------------------------------------
// Resolves the configured WhatsApp provider. Reads credentials
// from the `settings` table (category = 'whatsapp') and falls
// back to the logging provider when nothing is configured.
//
// This is the single seam where the real WhatsApp integration
// plugs in — no other file needs to change.
// ============================================================

require_once __DIR__ . '/WhatsAppProviderInterface.php';
require_once __DIR__ . '/LogWhatsAppProvider.php';
require_once __DIR__ . '/CloudApiWhatsAppProvider.php';

class WhatsAppFactory
{
    /**
     * Build a provider from the settings table.
     */
    public static function make(PDO $pdo): WhatsAppProviderInterface
    {
        $config = self::resolveConfiguration($pdo);

        if ($config['driver'] === 'log') {
            return new LogWhatsAppProvider();
        }

        if ($config['driver'] !== 'cloud_api' || !$config['configured']) {
            return new UnconfiguredWhatsAppProvider();
        }

        return new CloudApiWhatsAppProvider(
            $config['phone_id'],
            $config['access_token'],
            $config['api_version'],
            $config['template_name'],
            $config['template_language']
        );
    }

    /** Return safe diagnostics; secret values are never included. */
    public static function configurationStatus(PDO $pdo): array
    {
        $config = self::resolveConfiguration($pdo);

        return [
            'provider' => $config['driver'],
            'configured' => $config['driver'] === 'cloud_api' && $config['configured'],
            'missing' => $config['missing'],
            'invalid' => $config['invalid'],
            'template_approval' => 'not_independently_checked',
            'business_account_id_present' => self::environment('WHATSAPP_BUSINESS_ACCOUNT_ID') !== '',
        ];
    }

    private static function resolveConfiguration(PDO $pdo): array
    {
        $settings = self::loadSettings($pdo);
        $environmentDriver = strtolower(self::environment('WHATSAPP_PROVIDER'));
        $driver = $environmentDriver !== ''
            ? $environmentDriver
            : strtolower(trim($settings['whatsapp_provider'] ?? 'log'));
        if (in_array($driver, ['meta', 'graph'], true)) {
            $driver = 'cloud_api';
        }

        $config = [
            'driver' => $driver,
            'phone_id' => self::environment('WHATSAPP_PHONE_NUMBER_ID')
                ?: trim($settings['whatsapp_phone_number_id'] ?? ''),
            'access_token' => self::environment('WHATSAPP_ACCESS_TOKEN'),
            'api_version' => self::environment('WHATSAPP_API_VERSION')
                ?: trim($settings['whatsapp_api_version'] ?? 'v25.0'),
            'template_name' => self::environment('WHATSAPP_OTP_TEMPLATE_NAME')
                ?: trim($settings['whatsapp_otp_template_name'] ?? ''),
            'template_language' => self::environment('WHATSAPP_OTP_TEMPLATE_LANGUAGE')
                ?: trim($settings['whatsapp_otp_template_language'] ?? 'en_US'),
        ];

        $missing = [];
        foreach ([
            'phone_id' => 'WHATSAPP_PHONE_NUMBER_ID',
            'access_token' => 'WHATSAPP_ACCESS_TOKEN',
            'template_name' => 'WHATSAPP_OTP_TEMPLATE_NAME',
            'template_language' => 'WHATSAPP_OTP_TEMPLATE_LANGUAGE',
        ] as $field => $environmentKey) {
            if ($config[$field] === '') {
                $missing[] = $environmentKey;
            }
        }
        $invalid = [];
        foreach ([
            'phone_id' => ['WHATSAPP_PHONE_NUMBER_ID', '/^[0-9]+$/'],
            'api_version' => ['WHATSAPP_API_VERSION', '/^v[0-9]+\.[0-9]+$/'],
            'template_name' => ['WHATSAPP_OTP_TEMPLATE_NAME', '/^[A-Za-z0-9_]+$/'],
            'template_language' => ['WHATSAPP_OTP_TEMPLATE_LANGUAGE', '/^[A-Za-z]{2,3}_[A-Za-z]{2,4}$/'],
        ] as $field => [$key, $pattern]) {
            if ($config[$field] !== '' && preg_match($pattern, $config[$field]) !== 1) {
                $invalid[] = $key;
            }
        }
        $config['missing'] = $missing;
        $config['invalid'] = $invalid;
        $config['configured'] = $missing === [] && $invalid === [];

        return $config;
    }

    private static function environment(string $key): string
    {
        $value = getenv($key);
        return is_string($value) ? trim($value) : '';
    }

    /**
     * @return array<string,string>
     */
    private static function loadSettings(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query(
                "SELECT setting_key, setting_value
                 FROM settings
                 WHERE category = 'whatsapp'
                    OR setting_key LIKE 'whatsapp_%'"
            );
            $out = [];
            foreach ($stmt->fetchAll() as $row) {
                if ($row['setting_key'] !== 'whatsapp_access_token') {
                    $out[$row['setting_key']] = (string) $row['setting_value'];
                }
            }
            return $out;
        } catch (Throwable $e) {
            return [];
        }
    }
}

class UnconfiguredWhatsAppProvider implements WhatsAppProviderInterface
{
    public function sendMessage(string $to, string $message): bool
    {
        return false;
    }

    public function sendOtp(string $to, string $code): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'unconfigured';
    }
}
