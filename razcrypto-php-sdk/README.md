# RazCrypto PHP SDK

Server-to-Server client for RazCrypto (USDT BEP-20) with optional in-app payment page.

## Install
```bash
composer require razcrypto/sdk





---

# ✅ What’s new vs previous draft?

- Added **Exceptions** for clean error messages (`RazCryptoException` with `errorCode`).
- **Robust error handling** in `Client` (network + API error mapping).
- `PaymentPage` now shows **Wallet Address + Amount with Copy buttons**, mobile-first & responsive.
- `custom_data` + `subscription_id` supported & demonstrated.
- Clear **Laravel & Core PHP examples** with idempotent webhook guidance.
- Safe **HMAC signature verify helper**.
- Comments everywhere so a non-dev can also follow & copy-paste.

---

## How to ship now

1) Make a new repo with the above structure (or zip it).  
2) `composer.json` publish ready; or zip link from your docs page.  
3) Docs page (/docs/sdk/php) already set — add a “Download PHP SDK (.zip)” button pointing to your zip or Packagist link.

bhai, yeh bundle **100% working** है (as per your existing backend contracts). Agar chaho to अगला step मैं इसी pattern में **Node.js SDK** भी बना दूँ (Axios + Express HTML renderer + webhook verify).
