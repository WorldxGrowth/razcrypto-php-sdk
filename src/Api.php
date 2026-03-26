<?php
namespace RazCrypto;

class Api {
    private $gatewayId;
    private $secretKey;
    private $baseUrl = "https://razcryptogateway.com/api/v2";

    public $payment;

    public function __construct($gatewayId, $secretKey) {
        $this->gatewayId = $gatewayId;
        $this->secretKey = $secretKey;
        $this->payment = new Resource\Payment($this);
    }

    public function request($method, $path, $data = []) {
        // अगर path में पूरा URL है (जैसे v1 के लिए), तो उसे ही यूज़ करो, वरना v2 baseUrl जोड़ो
        $url = (strpos($path, 'http') === 0) ? $path : $this->baseUrl . $path;
        
        $ch = curl_init($url);
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Gateway-Id: ' . $this->gatewayId,
            'X-Secret-Key: ' . $this->secretKey
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);
        
        if ($httpCode >= 400) {
            throw new \Exception($result['message'] ?? "API Error: HTTP $httpCode");
        }

        return $result;
    }
}