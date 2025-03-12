<?php

namespace Azuriom\Plugin\PayUUPIPayment;

use Azuriom\Plugin\Shop\Cart\Cart;
use Azuriom\Plugin\Shop\Models\Payment;
use Azuriom\Plugin\Shop\Payment\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PayUUPIMethod extends PaymentMethod
{
    /**
     * The payment method id name.
     *
     * @var string
     */
    protected $id = 'payu-upi';

    /**
     * The payment method display name.
     *
     * @var string
     */
    protected $name = 'PayU UPI';

    /**
     * Start a new payment with PayU UPI and redirect to payment page.
     */
    public function startPayment(Cart $cart, float $amount, string $currency)
    {
        // Get the config values
        $merchantKey = $this->gateway->data['merchant-key'] ?? '';
        $merchantSalt = $this->gateway->data['merchant-salt'] ?? '';
        $testMode = $this->gateway->data['test-mode'] ?? false;
        
        // Create a new payment
        $payment = $this->createPayment($cart, $amount, $currency);
        
        // Generate txnid - unique transaction ID
        $txnid = Str::uuid()->toString();
        
        // Prepare the payment data
        $user = auth()->user();
        $productInfo = 'Purchase from '.site_name();
        
        // Create the data for PayU
        $data = [
            'key' => $merchantKey,
            'txnid' => $txnid,
            'amount' => $amount,
            'productinfo' => $productInfo,
            'firstname' => $user->name,
            'email' => $user->email,
            'phone' => '',
            'surl' => route('shop.payments.success', $this->id),
            'furl' => route('shop.payments.failure', $this->id),
            'service_provider' => 'payu_paisa',
            'udf1' => $payment->id, // Store payment ID in UDF1
        ];
        
        // Generate hash
        $hashSequence = "key|txnid|amount|productinfo|firstname|email|udf1||||||||||{$merchantSalt}";
        $hashSequenceValues = "{$data['key']}|{$data['txnid']}|{$data['amount']}|{$data['productinfo']}|{$data['firstname']}|{$data['email']}|{$data['udf1']}||||||||||{$merchantSalt}";
        $data['hash'] = strtolower(hash('sha512', $hashSequenceValues));
        
        // Update the payment with transaction ID
        $payment->update(['transaction_id' => $txnid]);
        
        // PayU gateway URL - use test or live URL based on the configuration
        $paymentUrl = $testMode ? 'https://sandboxsecure.payu.in/_payment' : 'https://secure.payu.in/_payment';
        
        // Return the view with the payment form
        return view('payuupipayment::checkout', [
            'paymentUrl' => $paymentUrl,
            'data' => $data,
        ]);
    }

    /**
     * Handle the payment notification (webhook) from PayU.
     */
    public function notification(Request $request, ?string $paymentId)
    {
        $merchantSalt = $this->gateway->data['merchant-salt'] ?? '';
        
        // Verify the response
        $status = $request->input('status');
        $txnid = $request->input('txnid');
        $amount = $request->input('amount');
        $paymentId = $request->input('udf1'); // Payment ID from UDF1
        
        // Calculate and verify hash
        $hashString = "{$merchantSalt}|{$status}||||||||||{$request->input('udf1')}|{$request->input('email')}|{$request->input('firstname')}|{$request->input('productinfo')}|{$amount}|{$txnid}|{$request->input('key')}";
        $calculatedHash = strtolower(hash('sha512', $hashString));
        
        if ($calculatedHash !== $request->input('hash')) {
            logger()->warning('[Shop] PayU UPI - Invalid hash: Received '.$request->input('hash').' but calculated '.$calculatedHash);
            return response('Hash mismatch', 400);
        }
        
        // Find the payment
        $payment = Payment::findOrFail($paymentId);
        
        // Process the payment based on the status
        if ($status === 'success') {
            // Verify that the amount matches
            if (floatval($amount) !== floatval($payment->price)) {
                logger()->warning("[Shop] PayU UPI - Amount mismatch for payment #{$payment->id}: {$amount} != {$payment->price}");
                return $this->invalidPayment($payment, $txnid, 'Amount mismatch');
            }
            
            // Update payment transaction ID if needed
            if ($payment->transaction_id !== $txnid) {
                $payment->transaction_id = $txnid;
                $payment->save();
            }
            
            // Process the payment
            return $this->processPayment($payment, $txnid);
        }
        
        // Payment failed
        logger()->warning("[Shop] PayU UPI - Payment failed for payment #{$payment->id}: {$status}");
        return $this->invalidPayment($payment, $txnid, "Payment failed: {$status}");
    }

    /**
     * Handle successful payment return from PayU.
     */
    public function success(Request $request)
    {
        // This method is optional as we process in notification above
        // You could add additional verification if needed
        
        return redirect()->route('shop.payments.success');
    }

    /**
     * Handle failed payment return from PayU.
     */
    public function failure(Request $request)
    {
        // This method is optional as we process in notification above
        
        return redirect()->route('shop.payments.failure');
    }

    /**
     * Get the view for the gateway config in the admin panel.
     */
    public function view(): string
    {
        return 'payuupipayment::admin.config';
    }

    /**
     * Get the validation rules for the gateway config in the admin panel.
     */
    public function rules(): array
    {
        return [
            'merchant-key' => ['required', 'string'],
            'merchant-salt' => ['required', 'string'],
            'test-mode' => ['boolean'],
        ];
    }

    /**
     * Get the payment method image.
     */
    public function image(): string
    {
        return asset('plugins/payuupipayment/img/payu-upi.png');
    }
}
