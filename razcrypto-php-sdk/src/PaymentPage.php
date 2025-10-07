<?php
namespace RazCrypto;

/**
 * Renders a minimal in-app payment page (no backend required here):
 * - Shows Logo + Amount + Wallet Address (copy buttons)
 * - Displays QR (from API qr_url)
 * - 5s polling to Status API → success overlay + chime sound
 * - Mobile-first, responsive UI. No external CSS/JS dependencies.
 *
 * SECURITY: This is a view helper. Real balance crediting must happen
 *           on your server via webhook (HMAC verify).
 */
class PaymentPage
{
    /**
     * Render the custom payment page.
     *
     * @param array $apiJson JSON returned by createPayment()
     *   Required keys:
     *     - payment_id, amount, qr_url, payment_url
     *   Optional keys:
     *     - wallet_address, currency, chain, expiry_minutes
     * @param array $brand
     *   - logo_url, primary_color
     */
    public static function render(array $apiJson, array $brand = []): void
    {
        // Read brand config (from params or env)
        $logo = $brand['logo_url'] ?? (getenv('RAZ_LOGO_URL') ?: '');
        $prim = $brand['primary_color'] ?? (getenv('RAZ_PRIMARY_COLOR') ?: '#4f46e5');

        // Extract required UI fields safely
        $pid     = htmlspecialchars((string)($apiJson['payment_id'] ?? ''), ENT_QUOTES);
        $qr      = htmlspecialchars((string)($apiJson['qr_url'] ?? ''), ENT_QUOTES);
        $amount  = htmlspecialchars((string)($apiJson['amount'] ?? ''), ENT_QUOTES);
        $address = htmlspecialchars((string)($apiJson['wallet_address'] ?? ''), ENT_QUOTES);
        $curr    = htmlspecialchars((string)($apiJson['currency'] ?? 'USDT'), ENT_QUOTES);
        $chain   = htmlspecialchars((string)($apiJson['chain'] ?? 'BSC'), ENT_QUOTES);

        // Derive status endpoint from payment_url to keep same host
        $purl = (string)($apiJson['payment_url'] ?? '');
        $statusUrl = '';
        if ($purl && $pid) {
            $statusUrl = str_replace('/pay/'.$pid, '/api/v1/payments/status/'.$pid, $purl);
        }
        $statusUrl = htmlspecialchars($statusUrl, ENT_QUOTES);

        // Timer (visual); if API returns expiry_minutes, use it
        $expiry = (int)($apiJson['expiry_minutes'] ?? 30);
        $expiry = max(1, min(60, $expiry));
        $seconds = $expiry * 60;

        echo <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pay | RazCrypto</title>
<style>
  :root{
    --primary: {$prim};
    --ink:#111827; --muted:#6b7280; --bg:#f7f8fb; --card:#ffffff; --border:#e5e7eb;
    --ok:#10b981; --danger:#ef4444;
  }
  html,body{margin:0;padding:0;background:var(--bg);color:var(--ink);font-family:Inter,ui-sans-serif,system-ui,Segoe UI,Roboto,Helvetica,Arial}
  .wrap{max-width:720px;margin:16px auto;padding:12px}
  .card{background:var(--card);border:1px solid var(--border);border-radius:16px;box-shadow:0 6px 24px rgba(0,0,0,.06);overflow:hidden}
  .head{display:flex;align-items:center;gap:12px;padding:16px;border-bottom:1px solid var(--border)}
  .head img{height:34px}
  .head .title{font-weight:800}
  .badge{margin-left:auto;background:var(--primary);color:#fff;border-radius:999px;padding:6px 10px;font-size:12px}
  .main{padding:16px;display:grid;gap:14px}
  .grid{display:grid;grid-template-columns:1fr;gap:12px}
  @media(min-width:760px){ .grid{grid-template-columns:1fr 1fr} }
  .panel{border:1px dashed var(--border);border-radius:12px;padding:12px;background:#fafbff}
  .row{display:flex;align-items:center;gap:8px}
  .label{font-size:12px;color:var(--muted)}
  .val{font-weight:700;word-break:break-all}
  .copy{margin-left:auto;font-size:12px;border:1px solid var(--border);background:#fff;border-radius:8px;padding:6px 10px;cursor:pointer}
  .qr{display:flex;justify-content:center}
  .qr img{width:240px;height:240px;border-radius:12px;border:1px solid var(--border);background:#fff}
  .hint{font-size:13px;color:var(--muted)}
  .footer{padding:12px;border-top:1px solid var(--border);text-align:center;font-size:12px;color:#6b7280}
  #overlay{position:fixed;inset:0;background:rgba(0,0,0,.65);display:none;align-items:center;justify-content:center;z-index:9999}
  #overlay .card2{background:#fff;padding:24px;border-radius:16px;text-align:center;box-shadow:0 12px 40px rgba(0,0,0,.3)}
  #overlay .ok{font-size:18px;font-weight:800;color:var(--ok)}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="head">
      <img src="{$logo}" alt="Logo" onerror="this.style.display='none'">
      <div class="title">Complete Your Payment</div>
      <span class="badge" id="timer">--:--</span>
    </div>

    <div class="main">
      <div class="grid">
        <div class="panel">
          <div class="label">Amount</div>
          <div class="row">
            <div class="val">{$amount} {$curr}</div>
            <button class="copy" data-copy="{$amount}" onclick="copyText(this)">Copy</button>
          </div>
        </div>

        <div class="panel">
          <div class="label">Wallet Address ({$chain})</div>
          <div class="row">
            <div class="val" id="addr">{$address}</div>
            <button class="copy" data-copy="{$address}" onclick="copyText(this)">Copy</button>
          </div>
        </div>
      </div>

      <div class="panel qr">
        <img src="{$qr}" alt="Payment QR">
      </div>

      <div class="hint">Scan the QR with your crypto wallet or copy the address & amount. This page auto-detects confirmation.</div>
      <div class="hint">Payment ID: <strong>{$pid}</strong></div>
    </div>

    <div class="footer">Secured by RazCrypto • Do not refresh after payment</div>
  </div>
</div>

<!-- Success Overlay -->
<div id="overlay">
  <div class="card2">
    <div class="ok">✅ Payment Confirmed</div>
    <div>Transaction credited. You may close this window.</div>
    <audio id="chime" src="https://razcryptogateway.com/assets/audio/success.mp3" preload="auto"></audio>
  </div>
</div>

<script>
  // Visual countdown (client-side only)
  let left = {$seconds};
  const timerEl = document.getElementById('timer');
  function fmt(s){ const m=Math.floor(s/60), ss=('0'+(s%60)).slice(-2); return m+':'+ss; }
  setInterval(()=>{ left=Math.max(0,left-1); timerEl.textContent=fmt(left); },1000);

  // Copy helper
  function copyText(btn){
    const v = btn.getAttribute('data-copy') || '';
    navigator.clipboard.writeText(v).then(()=>{
      btn.textContent = 'Copied';
      setTimeout(()=>btn.textContent='Copy', 1200);
    });
  }

  // Polling status every 5s (if URL could not be derived, no-op)
  const STATUS_URL = "{$statusUrl}";
  async function poll(){
    if(!STATUS_URL){ return; }
    try{
      const res = await fetch(STATUS_URL);
      if(res.ok){
        const j = await res.json();
        if(j.status === 'completed'){
          const ov = document.getElementById('overlay');
          ov.style.display = 'flex';
          try{ document.getElementById('chime').play().catch(()=>{}); }catch(e){}
          return; // stop polling
        }
        if(j.status === 'expired'){
          timerEl.textContent = 'Expired';
          return; // stop polling
        }
      }
    }catch(e){}
    setTimeout(poll, 5000);
  }
  poll();
</script>
</body>
</html>
HTML;
    }
}
