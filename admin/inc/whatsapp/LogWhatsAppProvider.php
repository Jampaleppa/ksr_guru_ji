<?php
// ============================================================
// admin/inc/whatsapp/LogWhatsAppProvider.php
// ------------------------------------------------------------
// Explicit development provider. It does not deliver messages or log
// recipients, message bodies, or OTP values.
//
// Use this only to represent an explicitly disabled development sender.
// OTPs must never be logged or surfaced through a development fallback.
// ============================================================

require_once __DIR__ . '/WhatsAppProviderInterface.php';

class LogWhatsAppProvider implements WhatsAppProviderInterface
{
    private string $logDir;

    public function __construct(?string $logDir = null)
    {
        $this->logDir = $logDir ?: (__DIR__ . '/../../../logs');
    }

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
        return 'log';
    }
}
