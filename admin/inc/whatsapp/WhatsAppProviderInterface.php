<?php
// ============================================================
// admin/inc/whatsapp/WhatsAppProviderInterface.php
// ------------------------------------------------------------
// Abstraction layer for WhatsApp delivery.
//
// The authentication system talks ONLY to this interface, so the
// real WhatsApp Business API can be wired in later without
// rewriting the OTP / notification flows.
// ============================================================

interface WhatsAppProviderInterface
{
    /**
     * Send a plain text message to a phone number.
     *
     * @param string $to      destination in E.164 or local format
     * @param string $message message body
     * @return bool true when the provider accepted the message
     */
    public function sendMessage(string $to, string $message): bool;

    /** Send a code using an approved authentication template. */
    public function sendOtp(string $to, string $code): bool;

    /**
     * Human-readable provider name (used in logs / settings UI).
     */
    public function name(): string;
}
