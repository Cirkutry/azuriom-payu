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

    <div class="mb-3">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" id="testModeInput" name="test-mode" @if(old('test-mode', $gateway->data['test-mode'] ?? false)) checked @endif>
            <label class="form-check-label" for="testModeInput">Enable test mode</label>
        </div>
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
</div>
