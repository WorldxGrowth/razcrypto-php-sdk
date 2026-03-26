<?php
namespace RazCrypto\Resource;

class Payment {
    private $api;

    public function __construct($api) {
        $this->api = $api;
    }

    /**
     * Create a new payment (V2)
     */
    public function create($params) {
        return $this->api->request('POST', '/payments/create', $params);
    }

    /**
     * Fetch payment status (V1)
     */
    public function fetch($paymentId) {
        // V1 URL को पूरा पास कर रहे हैं ताकि Api.php इसे डायरेक्ट कॉल करे
        $fullUrl = "https://razcryptogateway.com/api/v1/payments/status/{$paymentId}";
        return $this->api->request('GET', $fullUrl);
    }

    /**
     * Check Webhook Delivery Status (V2)
     */
    public function webhookStatus($paymentId, $authKey) {
        return $this->api->request('GET', "/webhooks/{$paymentId}/status?auth_key={$authKey}");
    }
}