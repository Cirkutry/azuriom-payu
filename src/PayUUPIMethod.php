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
     * PayU gateway URLs.
     * 
     * IMPORTANT: To switch between test and production mode, 
     * change the USE_TEST_MODE constant to true or false.
     */
    const USE_TEST_MODE = true; // Set to false for production
    
    // Different sandbox URLs to try if one isn't working
    const TEST_PAYMENT_URLS = [
        'https://test.payu.in/_payment',           // Primary test URL
        'https://sandboxsecure.payu.in/_payment',  // Alternative sandbox URL
        'https://test.payumoney.com/payment',      // PayUMoney test URL
    ];
    
    // Production URL
    const PRODUCTION_PAYMENT_URL = 'https://secure.payu.in/_payment';

    /**
     * Start a new payment with PayU UPI and redirect to payment page.
     */
    public function startPayment(Cart $cart, float $amount, string $currency)
    {
        // Get the config values
        $merchantKey = $this->gateway->data['merchant-key'] ?? '';
        $merchantSalt = $this->gateway->data['merchant-salt'] ?? '';
        
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
            'udf2' => '',
            'udf3' => '',
            'udf4' => '',
            'udf5' => '',
        ];
        
        // Generate hash
        // FIXED: The hash calculation according to PayU's formula
        // sha512(key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5||||||SALT)
        $hashString = "{$data['key']}|{$data['txnid']}|{$data['amount']}|{$data['productinfo']}|{$data['firstname']}|{$data['email']}|{$data['udf1']}|{$data['udf2']}|{$data['udf3']}|{$data['udf4']}|{$data['udf5']}||||||{$merchantSalt}";
        
        // Log the hash string for debugging
        logger()->debug("[Shop] PayU UPI - Hash string: {$hashString}");
        
        // Calculate the hash using SHA512
        $data['hash'] = hash('sha512', $hashString);
        
        // Log the calculated hash
        logger()->debug("[Shop] PayU UPI - Calculated hash: {$data['hash']}");
        
        // Update the payment with transaction ID
        $payment->update(['transaction_id' => $txnid]);
        
        // PayU gateway URL - determined by the USE_TEST_MODE constant
        $paymentUrl = self::USE_TEST_MODE ? self::TEST_PAYMENT_URLS[0] : self::PRODUCTION_PAYMENT_URL;
        
        // Log the payment attempt
        logger()->info("[Shop] PayU UPI - Starting payment #{$payment->id} with txnid {$txnid} to URL {$paymentUrl}");
        
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
        
        // Log incoming notification
        logger()->info("[Shop] PayU UPI - Received notification: " . json_encode($request->all()));
        
        // Verify the response
        $status = $request->input('status');
        $txnid = $request->input('txnid');
        $amount = $request->input('amount');
        $paymentId = $request->input('udf1'); // Payment ID from UDF1
        
        // Get all required fields for hash verification
        $key = $request->input('key');
        $productinfo = $request->input('productinfo');
        $firstname = $request->input('firstname');
        $email = $request->input('email');
        $udf1 = $request->input('udf1');
        $udf2 = $request->input('udf2', '');
        $udf3 = $request->input('udf3', '');
        $udf4 = $request->input('udf4', '');
        $udf5 = $request->input('udf5', '');
        
        // Calculate and verify hash for response
        // Formula for response hash: sha512(SALT|status||||||udf5|udf4|udf3|udf2|udf1|email|firstname|productinfo|amount|txnid|key)
        $hashString = "{$merchantSalt}|{$status}||||||{$udf5}|{$udf4}|{$udf3}|{$udf2}|{$udf1}|{$email}|{$firstname}|{$productinfo}|{$amount}|{$txnid}|{$key}";
        
        // Log the hash string for debugging
        logger()->debug("[Shop] PayU UPI - Response hash string: {$hashString}");
        
        $calculatedHash = hash('sha512', $hashString);
        
        // Log the calculated hash and received hash
        logger()->debug("[Shop] PayU UPI - Calculated response hash: {$calculatedHash}");
        logger()->debug("[Shop] PayU UPI - Received hash: " . $request->input('hash'));
        
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
        // Log success return
        logger()->info("[Shop] PayU UPI - Success return: " . json_encode($request->all()));
        
        return redirect()->route('shop.payments.success');
    }

    /**
     * Handle failed payment return from PayU.
     */
    public function failure(Request $request)
    {
        // Log failure return
        logger()->info("[Shop] PayU UPI - Failure return: " . json_encode($request->all()));
        
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
        ];
    }

    /**
     * Get the payment method image.
     */
    public function image(): string
    {
        return asset('plugins/payuupipayment/img/payu-upi.png');
    }
    
    /**
     * Try a different test URL if needed
     */
    public static function getTestUrl($index = 0)
    {
        $index = max(0, min(count(self::TEST_PAYMENT_URLS) - 1, $index));
        return self::TEST_PAYMENT_URLS[$index];
    }
}