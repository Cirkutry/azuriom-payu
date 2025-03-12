<?php

namespace Azuriom\Plugin\PayUUPIPayment\Providers;

use Azuriom\Extensions\Plugin\BasePluginServiceProvider;
use Azuriom\Plugin\PayUUPIPayment\PayUUPIMethod;

class PayUUPIPaymentServiceProvider extends BasePluginServiceProvider
{
    /**
     * Register any plugin services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any plugin services.
     */
    public function boot(): void
    {
        $this->loadViews();

        $this->loadTranslations();

        payment_manager()->registerPaymentMethod('payu-upi', PayUUPIMethod::class);
    }
}
