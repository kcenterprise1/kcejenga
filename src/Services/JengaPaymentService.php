<?php

namespace Kce\Kcejenga\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Exception;

class JengaPaymentService
{
    protected $client;
    protected $merchantCode;
    protected $consumerSecret;
    protected $apiKey;
    protected $privateKey;
    protected $environment;
    protected $callbackUrl;
    protected $tokenEndpoint;
    protected $paymentEndpoint;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => config('kcejenga.timeout', 90),
            'verify' => config('kcejenga.verify_ssl', env('APP_ENV') === 'production'),
        ]);

        $this->loadConfiguration();
    }

    /**
     * Load configuration from config file
     */
    protected function loadConfiguration()
    {
        $this->merchantCode = Config::get('kcejenga.merchant_code');
        $this->consumerSecret = Config::get('kcejenga.consumer_secret');
        $this->apiKey = Config::get('kcejenga.api_key');
        $this->privateKey = Config::get('kcejenga.private_key');
        $this->environment = Config::get('kcejenga.environment', 'sandbox');
        $this->callbackUrl = Config::get('kcejenga.callback_url', '/kcejenga/callback');

        $this->setEndpoints();
    }

    /**
     * Set API endpoints based on environment
     */
    protected function setEndpoints()
    {
        if ($this->environment === 'sandbox') {
            $this->tokenEndpoint = 'https://uat.finserve.africa/authentication/api/v3/authenticate/merchant';
            $this->paymentEndpoint = 'https://v3-uat.jengapgw.io/processPayment';
        } else {
            $this->tokenEndpoint = 'https://api.finserve.africa/authentication/api/v3/authenticate/merchant';
            $this->paymentEndpoint = 'https://v3.jengapgw.io/processPayment';
        }
    }

    /**
     * Authenticate and get access token
     *
     * @return string|null
     * @throws Exception
     */
    public function authenticate()
    {
        if (empty($this->merchantCode) || empty($this->consumerSecret) || empty($this->apiKey)) {
            throw new Exception('Missing required credentials. Please configure merchant code, consumer secret, and API key.');
        }

        try {
            $response = $this->client->post($this->tokenEndpoint, [
                'json' => [
                    'merchantCode' => $this->merchantCode,
                    'consumerSecret' => $this->consumerSecret,
                ],
                'headers' => [
                    'Api-Key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $responseBody = $response->getBody()->getContents();
            
            if (empty($responseBody)) {
                throw new Exception('Empty response from Jenga API');
            }
            
            $responseData = json_decode($responseBody, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response: ' . json_last_error_msg());
            }
            
            $statusCode = $response->getStatusCode();

            if ($statusCode === 200 && isset($responseData['accessToken'])) {
                return $responseData['accessToken'];
            }

            $message = $responseData['message'] ?? 'Authentication failed';
            
            if ($statusCode === 401) {
                $message = $statusCode . ': Authentication Error. Kindly contact us for support!';
            } elseif (in_array($statusCode, [500, 502, 504])) {
                $message = $statusCode . ': Internal Server Error. Kindly contact us for support!';
            } elseif ($statusCode === 404) {
                $message = $statusCode . ': Resource Not found Error. Kindly contact us for support!';
            }

            Log::error('Jenga Authentication Error', [
                'status_code' => $statusCode,
                'message' => $message,
                'response' => $responseData,
            ]);

            throw new Exception($message);

        } catch (RequestException $e) {
            $message = 'Failed to connect to Jenga API: ' . $e->getMessage();
            Log::error('Jenga API Request Exception', [
                'message' => $message,
                'exception' => $e,
            ]);
            throw new Exception($message);
        }
    }

    /**
     * Generate signature for payment
     *
     * @param string $orderReference
     * @param string $currency
     * @param float $amount
     * @param string|null $callbackUrl
     * @return string
     * @throws Exception
     */
    public function generateSignature($orderReference, $currency, $amount, $callbackUrl = null)
    {
        if (empty($this->privateKey) || strlen($this->privateKey) <= 250) {
            return '';
        }

        $callbackUrl = $callbackUrl ?? url($this->callbackUrl);
        
        // Build signature data string
        $data = $this->merchantCode . $orderReference . $currency . $amount . $callbackUrl;

        $privateKey = openssl_pkey_get_private($this->privateKey);
        
        if ($privateKey === false) {
            $error = openssl_error_string();
            Log::error('Failed to load private key', ['error' => $error]);
            throw new Exception('Failed to load private key: ' . $error);
        }

        $signature = '';
        if (!openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            $error = openssl_error_string();
            Log::error('Failed to sign data', ['error' => $error]);
            throw new Exception('Failed to sign data: ' . $error);
        }

        return base64_encode($signature);
    }

    /**
     * Initiate payment and return payment data
     *
     * @param array $paymentData
     * @return array
     * @throws Exception
     */
    public function initiatePayment(array $paymentData)
    {
        // Validate required fields according to official Jenga API
        // Reference: https://developer.jengahq.io/guides/jenga-pgw/checkout-reference
        $required = ['orderReference', 'orderAmount', 'currency', 'customerFirstName', 
                    'customerLastName', 'customerEmail', 'customerPhone', 'countryCode'];
        
        foreach ($required as $field) {
            if (!isset($paymentData[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        // Validate orderReference: Min 8 characters, alphanumeric
        if (strlen($paymentData['orderReference']) < 8) {
            throw new Exception("orderReference must be at least 8 characters long");
        }
        if (!preg_match('/^[a-zA-Z0-9]+$/', $paymentData['orderReference'])) {
            throw new Exception("orderReference must be alphanumeric");
        }

        // Validate productDescription: Max 200 characters
        if (isset($paymentData['productDescription']) && strlen($paymentData['productDescription']) > 200) {
            throw new Exception("productDescription must not exceed 200 characters");
        }

        // Validate amount format
        if (!is_numeric($paymentData['orderAmount']) || $paymentData['orderAmount'] <= 0) {
            throw new Exception('orderAmount must be a positive number');
        }

        // Get access token
        $token = $this->authenticate();
        if (empty($token)) {
            throw new Exception('Failed to obtain access token');
        }

        // Build callback URL
        $callbackUrl = $paymentData['callbackUrl'] ?? url($this->callbackUrl);
        
        // Generate signature
        $signature = '';
        try {
            $signature = $this->generateSignature(
                $paymentData['orderReference'],
                $paymentData['currency'],
                $paymentData['orderAmount'],
                $callbackUrl
            );
        } catch (Exception $e) {
            Log::warning('Signature generation failed, proceeding without signature', [
                'error' => $e->getMessage()
            ]);
        }

        // Format paymentTimeLimit (should be in format like "15mins" according to docs)
        $paymentTimeLimit = $paymentData['paymentTimeLimit'] ?? '15mins';
        // If numeric, assume minutes and append "mins"
        if (is_numeric($paymentTimeLimit) && strpos($paymentTimeLimit, 'mins') === false) {
            $paymentTimeLimit = $paymentTimeLimit . 'mins';
        }

        // Build payment form data according to official Jenga API specification
        $formData = [
            'token' => $token,
            'signature' => $signature,
            'merchantCode' => $this->merchantCode,
            'currency' => $paymentData['currency'],
            'countryCode' => $paymentData['countryCode'], // Required field
            'orderAmount' => $paymentData['orderAmount'],
            'orderReference' => $paymentData['orderReference'],
            'productType' => $paymentData['productType'] ?? 'Product',
            'productDescription' => $paymentData['productDescription'] ?? 'Payment via Jenga Gateway',
            'extraData' => $paymentData['extraData'] ?? '',
            'paymentTimeLimit' => $paymentTimeLimit,
            'customerFirstName' => $paymentData['customerFirstName'],
            'customerLastName' => $paymentData['customerLastName'],
            'customerEmail' => $paymentData['customerEmail'],
            'customerPhone' => preg_replace('/[^0-9]/', '', $paymentData['customerPhone']),
            'customerPostalCodeZip' => $paymentData['customerPostalCodeZip'] ?? '',
            'customerAddress' => $paymentData['customerAddress'] ?? '',
            'callbackUrl' => $callbackUrl,
            'secondaryReference' => $paymentData['secondaryReference'] ?? '', // Optional field
            'orderItems' => isset($paymentData['orderItems']) 
                ? json_encode($paymentData['orderItems']) 
                : json_encode([]),
        ];

        return [
            'payment_url' => $this->paymentEndpoint,
            'form_data' => $formData,
            'method' => 'POST',
        ];
    }

    /**
     * Get payment endpoint URL
     *
     * @return string
     */
    public function getPaymentEndpoint()
    {
        return $this->paymentEndpoint;
    }

    /**
     * Update configuration dynamically
     *
     * @param array $config
     * @return void
     */
    public function updateConfiguration(array $config)
    {
        if (isset($config['merchant_code'])) {
            $this->merchantCode = $config['merchant_code'];
        }
        if (isset($config['consumer_secret'])) {
            $this->consumerSecret = $config['consumer_secret'];
        }
        if (isset($config['api_key'])) {
            $this->apiKey = $config['api_key'];
        }
        if (isset($config['private_key'])) {
            $this->privateKey = $config['private_key'];
        }
        if (isset($config['environment'])) {
            $this->environment = $config['environment'];
            $this->setEndpoints();
        }
        if (isset($config['callback_url'])) {
            $this->callbackUrl = $config['callback_url'];
        }
    }
}

