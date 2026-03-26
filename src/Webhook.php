<?php
namespace RazCrypto;

class Webhook {
    /**
     * Verify the webhook signature from RazCrypto
     */
    public static function verifySignature($rawPayload, $signature, $secretKey) {
        $expectedSignature = hash_hmac('sha256', $rawPayload, $secretKey);
        
        // Use hash_equals to prevent timing attacks
        return hash_equals($expectedSignature, $signature);
    }
}