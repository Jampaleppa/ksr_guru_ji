<?php
// ============================================================
// admin/inc/whatsapp/CloudApiWhatsAppProvider.php
// ------------------------------------------------------------
// Production-ready provider for the Meta (Facebook) WhatsApp
// Cloud API using the Graph API. Configure via constructor or
// the constants pulled from the `settings` table.
//
// No third-party SDK is required: it performs a direct HTTPS
// call with cURL, which keeps the abstraction portable. Credentials are
// resolved by WhatsAppFactory from server configuration, never the client.
// ============================================================

require_once __DIR__ . '/WhatsAppProviderInterface.php';

class CloudApiWhatsAppProvider implements WhatsAppProviderInterface
{
    private string $phoneNumberId;
    private string $accessToken;
    private string $apiVersion;
    private string $otpTemplateName;
    private string $otpTemplateLanguage;
    private ?Closure $transport;
    private string $lastErrorCode = '';

    public function __construct(
        string $phoneNumberId,
        string $accessToken,
        string $apiVersion = 'v25.0',
        string $otpTemplateName = '',
        string $otpTemplateLanguage = 'en_US',
        ?callable $transport = null
    ) {
        $this->phoneNumberId = $phoneNumberId;
        $this->accessToken   = $accessToken;
        $this->apiVersion    = $apiVersion;
        $this->otpTemplateName = $otpTemplateName;
        $this->otpTemplateLanguage = $otpTemplateLanguage;
        $this->transport = $transport === null ? null : Closure::fromCallable($transport);
    }

    public function sendMessage(string $to, string $message): bool
    {
        if ($this->phoneNumberId === '' || $this->accessToken === '') {
            return false;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => preg_replace('/[^0-9]/', '', $to),
            'type'              => 'text',
            'text'              => [
                'preview_url' => false,
                'body'        => $message,
            ],
        ];

        return $this->sendPayload($to, $payload);
    }

    public function sendOtp(string $to, string $code): bool
    {
        if ($this->otpTemplateName === '' || $this->otpTemplateLanguage === '') {
            $this->lastErrorCode = 'TEMPLATE_CONFIGURATION_MISSING';
            return false;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                 => preg_replace('/[^0-9]/', '', $to),
            'type'               => 'template',
            'template'           => [
                'name'       => $this->otpTemplateName,
                'language'   => ['code' => $this->otpTemplateLanguage],
                'components' => [
                    [
                        'type'       => 'body',
                        'parameters' => [['type' => 'text', 'text' => $code]],
                    ],
                    [
                        'type'       => 'button',
                        'sub_type'   => 'url',
                        'index'      => '0',
                        'parameters' => [['type' => 'text', 'text' => $code]],
                    ],
                ],
            ],
        ];

        return $this->sendPayload($to, $payload);
    }

    /** Check the configured authentication template without sending a message. */
    public function verifyOtpTemplate(string $businessAccountId): array
    {
        if (!preg_match('/^[0-9]+$/', $businessAccountId)
            || !preg_match('/^v[0-9]+\.[0-9]+$/', $this->apiVersion)
            || $this->accessToken === '') {
            return ['status' => 'configuration_error', 'error_code' => 'CONFIGURATION_INVALID'];
        }

        $query = http_build_query([
            'name' => $this->otpTemplateName,
            'fields' => 'name,status,language,category',
        ]);
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/message_templates?%s',
            $this->apiVersion,
            $businessAccountId,
            $query
        );
        $headers = ['Authorization: Bearer ' . $this->accessToken];
        [$httpCode, $response, $transportFailed] = $this->request($url, $headers, [], 'GET');

        if ($transportFailed) {
            return ['status' => 'unavailable', 'error_code' => 'TRANSPORT_ERROR'];
        }

        $decoded = json_decode($response, true);
        if ($httpCode < 200 || $httpCode >= 300) {
            $error = is_array($decoded) && is_array($decoded['error'] ?? null)
                ? $decoded['error']
                : [];
            $code = isset($error['code']) && is_numeric($error['code'])
                ? (string)(int)$error['code']
                : 'HTTP_' . $httpCode;
            return ['status' => 'api_error', 'error_code' => $code];
        }

        if (!is_array($decoded) || !is_array($decoded['data'] ?? null)) {
            return ['status' => 'unavailable', 'error_code' => 'INVALID_PROVIDER_RESPONSE'];
        }

        foreach ($decoded['data'] as $template) {
            if (($template['name'] ?? '') !== $this->otpTemplateName
                || ($template['language'] ?? '') !== $this->otpTemplateLanguage) {
                continue;
            }
            if (strtoupper((string)($template['status'] ?? '')) !== 'APPROVED'
                || strtoupper((string)($template['category'] ?? '')) !== 'AUTHENTICATION') {
                return ['status' => 'not_approved', 'error_code' => 'TEMPLATE_NOT_APPROVED'];
            }
            return ['status' => 'approved', 'error_code' => ''];
        }

        return ['status' => 'not_found', 'error_code' => 'TEMPLATE_NOT_FOUND'];
    }

    private function sendPayload(string $to, array $payload): bool
    {
        $this->lastErrorCode = '';
        if (!preg_match('/^[0-9]+$/', $this->phoneNumberId)
            || $this->accessToken === ''
            || !preg_match('/^v[0-9]+\.[0-9]+$/', $this->apiVersion)) {
            $this->lastErrorCode = 'CLOUD_API_CONFIGURATION_INVALID';
            return false;
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $this->apiVersion,
            $this->phoneNumberId
        );

        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
        ];

        [$httpCode, $response, $transportFailed] = $this->request($url, $headers, $payload, 'POST');
        if ($transportFailed) {
            return $this->fail('TRANSPORT_ERROR');
        }

        $decoded = json_decode($response, true);
        if ($httpCode < 200 || $httpCode >= 300) {
            $error = is_array($decoded) && is_array($decoded['error'] ?? null)
                ? $decoded['error']
                : [];
            $code = isset($error['code']) && is_numeric($error['code'])
                ? (string)(int)$error['code']
                : 'HTTP_' . $httpCode;
            if (isset($error['error_subcode']) && is_numeric($error['error_subcode'])) {
                $code .= '_' . (int)$error['error_subcode'];
            }
            return $this->fail($code);
        }

        if (!is_array($decoded) || empty($decoded['messages'][0]['id'])) {
            return $this->fail('INVALID_PROVIDER_RESPONSE');
        }

        return true;
    }

    private function fail(string $code): bool
    {
        $this->lastErrorCode = preg_replace('/[^A-Z0-9_.-]/', '', strtoupper($code)) ?: 'SEND_FAILED';
        error_log('[WhatsApp CloudAPI] message request failed; code=' . $this->lastErrorCode);
        return false;
    }

    private function request(string $url, array $headers, array $payload, string $method): array
    {
        if ($this->transport !== null) {
            try {
                $result = ($this->transport)($url, $headers, $payload, $method);
                return [
                    (int)($result['status'] ?? 0),
                    (string)($result['body'] ?? ''),
                    false,
                ];
            } catch (Throwable $e) {
                return [0, '', true];
            }
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return [0, '', true];
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => $headers,
        ];
        if ($method === 'GET') {
            $options[CURLOPT_HTTPGET] = true;
        } else {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }

        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlFailed = $response === false || curl_errno($ch) !== 0;
        unset($ch);

        return [$httpCode, is_string($response) ? $response : '', $curlFailed];
    }

    public function lastErrorCode(): string
    {
        return $this->lastErrorCode;
    }

    public function name(): string
    {
        return 'cloud_api';
    }
}
