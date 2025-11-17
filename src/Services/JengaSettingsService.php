<?php

namespace Kce\Kcejenga\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Exception;

class JengaSettingsService
{
    protected $settings = [];
    protected $configPath;

    public function __construct()
    {
        $this->configPath = config_path('kcejenga.php');
        $this->loadSettings();
    }

    /**
     * Load current settings from config
     */
    protected function loadSettings()
    {
        $this->settings = [
            'merchant_code' => Config::get('kcejenga.merchant_code'),
            'consumer_secret' => Config::get('kcejenga.consumer_secret'),
            'api_key' => Config::get('kcejenga.api_key'),
            'private_key' => Config::get('kcejenga.private_key'),
            'environment' => Config::get('kcejenga.environment', 'sandbox'),
            'callback_url' => Config::get('kcejenga.callback_url', '/kcejenga/callback'),
        ];
    }

    /**
     * Set merchant code
     *
     * @param string $code
     * @return $this
     */
    public function setMerchantCode($code)
    {
        $this->settings['merchant_code'] = $code;
        return $this;
    }

    /**
     * Set consumer secret
     *
     * @param string $secret
     * @return $this
     */
    public function setConsumerSecret($secret)
    {
        $this->settings['consumer_secret'] = $secret;
        return $this;
    }

    /**
     * Set API key
     *
     * @param string $key
     * @return $this
     */
    public function setApiKey($key)
    {
        $this->settings['api_key'] = $key;
        return $this;
    }

    /**
     * Set private key
     *
     * @param string $key
     * @return $this
     */
    public function setPrivateKey($key)
    {
        $this->settings['private_key'] = $key;
        return $this;
    }

    /**
     * Set environment (sandbox or production)
     *
     * @param string $env
     * @return $this
     */
    public function setEnvironment($env)
    {
        if (!in_array(strtolower($env), ['sandbox', 'production'])) {
            throw new Exception('Environment must be either "sandbox" or "production"');
        }
        $this->settings['environment'] = strtolower($env);
        return $this;
    }

    /**
     * Set callback URL
     *
     * @param string $url
     * @return $this
     */
    public function setCallbackUrl($url)
    {
        $this->settings['callback_url'] = $url;
        return $this;
    }

    /**
     * Get all settings
     *
     * @return array
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * Get a specific setting
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getSetting($key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Validate that required settings are configured
     *
     * @return bool
     * @throws Exception
     */
    public function validateSettings()
    {
        $required = ['merchant_code', 'consumer_secret', 'api_key'];
        $missing = [];

        foreach ($required as $field) {
            if (empty($this->settings[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new Exception('Missing required settings: ' . implode(', ', $missing));
        }

        return true;
    }

    /**
     * Save settings to config (runtime update)
     * Note: For persistence, update your .env file or config file directly
     *
     * @return bool
     * @throws Exception
     */
    public function save()
    {
        // Validate before saving
        $this->validateSettings();

        // Update config at runtime
        foreach ($this->settings as $key => $value) {
            Config::set("kcejenga.{$key}", $value);
        }

        // Clear config cache if it exists
        if (app()->configurationIsCached()) {
            Artisan::call('config:clear');
        }

        // Reload settings from updated config
        $this->loadSettings();

        return true;
    }

    /**
     * Update settings from array
     *
     * @param array $settings
     * @return $this
     */
    public function update(array $settings)
    {
        foreach ($settings as $key => $value) {
            if (isset($this->settings[$key])) {
                $this->settings[$key] = $value;
            }
        }
        return $this;
    }

    /**
     * Test connection with current settings
     *
     * @return array
     */
    public function testConnection()
    {
        try {
            $this->validateSettings();
            
            $paymentService = app(JengaPaymentService::class);
            $paymentService->updateConfiguration($this->settings);
            
            $token = $paymentService->authenticate();
            
            return [
                'success' => true,
                'message' => 'Connection successful',
                'token_received' => !empty($token),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get a fresh instance for fluent interface
     *
     * @return static
     */
    public static function configure()
    {
        return new static();
    }
}

