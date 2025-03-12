<div class="row g-3">
    <div class="mb-3 col-md-6">
        <label class="form-label" for="merchantKeyInput">{{ trans('shop::admin.gateways.public-key') }}</label>
        <input type="text" class="form-control @error('merchant-key') is-invalid @enderror" id="merchantKeyInput" name="merchant-key" value="{{ old('merchant-key', $gateway->data['merchant-key'] ?? '') }}" required>

        @error('merchant-key')
        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
        @enderror
    </div>

    <div class="mb-3 col-md-6">
        <label class="form-label" for="merchantSaltInput">{{ trans('shop::admin.gateways.private-key') }}</label>
        <input type="text" class="form-control @error('merchant-salt') is-invalid @enderror" id="merchantSaltInput" name="merchant-salt" value="{{ old('merchant-salt', $gateway->data['merchant-salt'] ?? '') }}" required>

        @error('merchant-salt')
        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
        @enderror
    </div>
</div>

<div class="alert alert-info">
    <p>
        <i class="bi bi-info-circle"></i>
        @lang('payuupipayment::messages.setup', [
            'url' => '<code>'.route('home').'</code>',
            'ipn' => '<code>'.route('shop.payments.notification', 'payu-upi').'</code>',
        ])
    </p>
    <p>
        <i class="bi bi-info-circle"></i>
        To switch between test and production modes, you need to modify the 'USE_TEST_MODE' constant in the PayUUPIMethod.php file:
        <ul>
            <li>For test mode: Set <code>USE_TEST_MODE = true</code></li>
            <li>For production mode: Set <code>USE_TEST_MODE = false</code></li>
        </ul>
    </p>
</div>

<div class="alert alert-warning">
    <p>
        <i class="bi bi-exclamation-triangle"></i>
        <strong>Known Issues with PayU Sandbox:</strong>
    </p>
    <p>
        The PayU sandbox environment may occasionally experience timeouts or connection issues. If this happens, you can try:
    </p>
    <ol>
        <li>
            Using one of the alternative sandbox URLs by modifying the <code>getTestUrl()</code> method call in PayUUPIMethod.php:
            <ul>
                <li><code>getTestUrl(0)</code> - Primary: <code>https://test.payu.in/_payment</code></li>
                <li><code>getTestUrl(1)</code> - Alternative: <code>https://sandboxsecure.payu.in/_payment</code></li>
                <li><code>getTestUrl(2)</code> - PayUMoney: <code>https://test.payumoney.com/payment</code></li>
            </ul>
        </li>
        <li>Check if PayU's sandbox servers are currently available</li>
        <li>Consider testing with the production URL if the sandbox continues to have issues</li>
    </ol>
</div>