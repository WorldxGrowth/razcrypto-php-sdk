<?php
namespace RazCrypto;

use GuzzleHttp\Client as Http;
use GuzzleHttp\Exception\GuzzleException;
use RazCrypto\Exceptions\RazCryptoException;
use RazCrypto\Support\Arr;

/**
 * RazCrypto PHP SDK - Server-to-Server client
 *
 * Features:
 * - createPayment(): hits /api/v1/payments/create with gateway_id+secret_key
 * - getStatus():     hits /api/v1/payments/status/{payment_id}
 * - verifyWebhook(): HMAC-SHA256 signature verification on raw body
 *
 * Notes:
 * - NEVER expose your secret key in frontend. Keep it server-side.
 * - Prefer reading credentials from your app's .env.
 */
class Client
{
    /** @var string Gateway ID from dashboard */
    private string $gatewayId;

    /** @var string Secret key from dashboard (server-side only) */
    private string $secretKey;

    /** @var string Base URL for API */
    private string $baseUrl;

    /** @var Http Guzzle HTTP client */
    private Http $http;

    public function __construct(
        string $gatewayId,
        string $secretKey,
        string $baseUrl = 'https://razcryptogateway.com/api/v1',
        float $timeoutSeconds = 12.0
    ) {
        if (!$gatewayId || !$secretKey) {
            throw new RazCryptoException('Gateway credentials missing. Provide gatewayId & secretKey.');
        }

        $this->gatewayId = $gatewayId;
        $this->secretKey = $secretKey;
        $this->baseUrl   = rtrim($baseUrl, '/');
        $this->http      = new Http(['timeout' => $timeoutSeconds]);
    }

    /**
     * Create payment
     *
     * @param float $amount  Amount >= 0.01
     * @param array $opts    Optional fields:
     *   - callback_url, email, mobile, username, product_id, subscription_id
     *   - custom_data (assoc array)
     *   - expiry_minutes (1..60)
     *   - currency, chain  (optional)
     *   - return_json => "true" (default) - keep as string for backend compatibility
     *
     * @return array API JSON (throws on HTTP/network issues)
     * @throws RazCryptoException
     */
    public function createPayment(float $amount, array $opts = []): array
    {
        if (!is_finite($amount) || $amount <= 0) {
            throw new RazCryptoException('Amount must be > 0.01 (given: ' . $amount . ')', 'RZ_001');
        }

        // Payload base (server-to-server auth)
        $payload = array_merge([
            'gateway_id'  => $this->gatewayId,
            'secret_key'  => $this->secretKey,
            'amount'      => $amount,
            'return_json' => 'true',
        ], $opts);

        // Ensure custom_data is an object or array if provided
        if (isset($payload['custom_data']) && !is_array($payload['custom_data'])) {
            throw new RazCryptoException('custom_data must be a JSON object/assoc array.');
        }

        try {
            $res  = $this->http->post($this->baseUrl . '/payments/create', ['json' => $payload]);
            $code = $res->getStatusCode();
            $json = json_decode((string) $res->getBody(), true);

            if ($code >= 400) {
                throw new RazCryptoException('HTTP ' . $code . ' from API.');
            }
            if (!is_array($json)) {
                throw new RazCryptoException('Invalid JSON from API.');
            }
            if (Arr::get($json, 'status') !== 'success') {
                // Map a clean message & pass error_code if present
                $msg = Arr::get($json, 'message', 'Payment creation failed');
                $err = Arr::get($json, 'error_code');
                throw new RazCryptoException($msg, $err);
            }
            return $json;

        } catch (GuzzleException $e) {
            // Network/timeouts handled here
            throw new RazCryptoException('Network error: ' . $e->getMessage());
        }
    }

    /**
     * Get payment status (pending/completed/expired/not_found)
     *
     * @param string $paymentId
     * @param int    $expiryMinutes (1..60)
     * @return array
     * @throws RazCryptoException
     */
    public function getStatus(string $paymentId, int $expiryMinutes = 30): array
    {
        if (!$paymentId) {
            throw new RazCryptoException('paymentId is required.');
        }
        $m = max(1, min(60, $expiryMinutes));
        $url = $this->baseUrl . '/payments/status/' . rawurlencode($paymentId) . '?m=' . $m;

        try {
            $res  = $this->http->get($url);
            $code = $res->getStatusCode();
            $json = json_decode((string) $res->getBody(), true);

            if ($code >= 400) {
                throw new RazCryptoException('HTTP ' . $code . ' from API.');
            }
            if (!is_array($json)) {
                throw new RazCryptoException('Invalid JSON from API.');
            }
            return $json;

        } catch (GuzzleException $e) {
            throw new RazCryptoException('Network error: ' . $e->getMessage());
        }
    }

    /**
     * Verify webhook signature (HMAC-SHA256 on raw body with secret)
     *
     * @param string $rawBody       Raw body string as received (DO NOT re-encode)
     * @param string $receivedSig   From header "x-razcrypto-signature"
     * @param string $secret        Your secret key
     * @return bool
     */
    public static function verifyWebhook(string $rawBody, string $receivedSig, string $secret): bool
    {
        if ($receivedSig === '' || $secret === '') return false;
        $expected = hash_hmac('sha256', $rawBody, $secret);
        // timing-safe compare
        return hash_equals($expected, $receivedSig);
    }
}
