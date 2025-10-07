<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use RazCrypto\Client;
use RazCrypto\PaymentPage;
use RazCrypto\Exceptions\RazCryptoException;

class DepositController extends Controller
{
    /**
     * Handle Add Fund form submit.
     * Validates, creates payment, and either redirects (hosted) or renders SDK page.
     */
    public function create(Request $req)
    {
        $data = $req->validate([
            'amount'   => ['required','numeric','min:0.01'],
            'email'    => ['required','email'],
            'username' => ['required','string','max:100'],
            'mobile'   => ['nullable','string','max:30'],
            // Optional: product/subscription/custom_data
        ]);

        $raz = new Client(env('RAZ_GATEWAY_ID'), env('RAZ_SECRET_KEY'));

        try {
            $payment = $raz->createPayment((float)$data['amount'], [
                'callback_url'   => route('raz.webhook'),      // dashboard URL can also be used
                'email'          => $data['email'],
                'username'       => $data['username'],
                'mobile'         => $data['mobile'] ?? null,
                'product_id'     => $req->input('product_id'),
                'subscription_id'=> $req->input('subscription_id'),
                // custom JSON passthrough (webhook + responses)
                'custom_data'    => [
                    'order_id'  => 'ORD-' . now()->timestamp,
                    'source'    => 'laravel_demo',
                ],
            ]);
        } catch (RazCryptoException $e) {
            // Show friendly message
            return back()->withErrors([
                'amount' => $e->getMessage() . ($e->getErrorCode() ? ' ('.$e->getErrorCode().')' : '')
            ])->withInput();
        }

        if (($payment['status'] ?? '') !== 'success') {
            return back()->withErrors(['amount' => $payment['message'] ?? 'Payment failed'])->withInput();
        }

        // Switch: Hosted vs Custom page
        if (filter_var(env('RAZ_REDIRECT', true), FILTER_VALIDATE_BOOLEAN) && !empty($payment['payment_url'])) {
            return redirect($payment['payment_url']);
        }

        // Render SDK page (QR + address + amount + polling)
        // IMPORTANT: PaymentPage::render echoes HTML — wrap with response()
        return response(PaymentPage::render($payment, [
            'logo_url'      => env('RAZ_LOGO_URL'),
            'primary_color' => env('RAZ_PRIMARY_COLOR'),
        ]));
    }
}
