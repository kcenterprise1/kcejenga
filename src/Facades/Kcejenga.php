<?php

namespace Kce\Kcejenga\Facades;

use Illuminate\Support\Facades\Facade;
use Kce\Kcejenga\Services\JengaPaymentService;
use Kce\Kcejenga\Services\JengaSettingsService;

/**
 * @method static string authenticate()
 * @method static string generateSignature(string $orderReference, string $currency, float $amount, string|null $callbackUrl = null)
 * @method static array initiatePayment(array $paymentData)
 * @method static string getPaymentEndpoint()
 * @method static void updateConfiguration(array $config)
 * @method static JengaSettingsService configure()
 * @method static JengaSettingsService setMerchantCode(string $code)
 * @method static JengaSettingsService setConsumerSecret(string $secret)
 * @method static JengaSettingsService setApiKey(string $key)
 * @method static JengaSettingsService setPrivateKey(string $key)
 * @method static JengaSettingsService setEnvironment(string $env)
 * @method static JengaSettingsService setCallbackUrl(string $url)
 * @method static array getSettings()
 * @method static mixed getSetting(string $key, mixed $default = null)
 * @method static bool validateSettings()
 * @method static bool save()
 * @method static JengaSettingsService update(array $settings)
 * @method static array testConnection()
 *
 * @see \Kce\Kcejenga\Services\JengaPaymentService
 * @see \Kce\Kcejenga\Services\JengaSettingsService
 */
class Kcejenga extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'kcejenga';
    }

    /**
     * Get payment service instance
     *
     * @return JengaPaymentService
     */
    public static function payment()
    {
        return app(JengaPaymentService::class);
    }

    /**
     * Get settings service instance
     *
     * @return JengaSettingsService
     */
    public static function settings()
    {
        return app(JengaSettingsService::class);
    }

    /**
     * Configure settings using fluent interface
     *
     * @return JengaSettingsService
     */
    public static function configure()
    {
        return JengaSettingsService::configure();
    }
}

