# KCE Jenga Payment Gateway Package - Laravel Payment Processing for Kenya

[![Latest Stable Version](https://img.shields.io/packagist/v/kce/kcejenga.svg)](https://packagist.org/packages/kce/kcejenga)
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](https://github.com/koech-connect/kcejenga/blob/main/LICENSE)
[![Built with Laravel](https://img.shields.io/badge/built%20with-Laravel-brightgreen.svg)](https://laravel.com)

## Overview

**KCE Jenga Payment Gateway Package** is a premium, enterprise-grade Laravel payment processing solution developed by [Koech Connect Enterprise](https://koechconnect.com), a leading IT solutions provider in Kenya. This package provides seamless integration with multiple payment providers including **M-Pesa**, **Equity Bank**, **Mastercard**, and **Visa**, enabling Kenyan businesses to accept both local and international payments.

### Why Choose KCE Jenga?

- **Kenya-Focused**: Built specifically for Kenyan payment systems and business requirements
- **DevSecOps Certified**: High-security payment processing with enterprise-grade encryption
- **API Integration Experts**: Proven expertise integrating M-Pesa, Equity Bank, Mastercard, and Visa APIs
- **Production Ready**: Used by enterprises across Kenya for critical payment operations
- **Comprehensive Documentation**: Complete guides for Laravel developers in Kenya and beyond

---

# KCE Jenga Payment Gateway Package

A Laravel package for integrating Jenga Payment Gateway into your Laravel applications. This package provides a clean, easy-to-use interface for processing payments through Jenga's payment gateway.

## Author

**Koech Connect Enterprise (KCE)**  
Email: info@kcenterprise.top

## Features

- ✅ Easy payment initiation following [official Jenga API specification](https://developer.jengahq.io/guides/jenga-pgw/checkout-reference)
- ✅ Payment callback handling with proper field mapping
- ✅ Transaction storage and tracking
- ✅ Settings management interface (web & API)
- ✅ Fluent configuration interface
- ✅ Support for both Sandbox and Production environments
- ✅ RSA signature generation for secure payments (when secure mode is enabled)
- ✅ Comprehensive validation (orderReference format, productDescription length, etc.)
- ✅ Comprehensive error handling and logging
- ✅ Hash verification support for callback security

## Installation

### Via Composer

```bash
composer require kce/kcejenga
```

### Publish Configuration

```bash
php artisan vendor:publish --tag=kcejenga-config
```

### Run Migrations

```bash
php artisan migrate
```

## Configuration

### Getting Your Credentials

To use Jenga Payment Gateway, you need to:

1. **Register at Jenga HQ**: Visit [Jenga HQ](https://v3.jengahq.io/) to create your merchant account
2. **Get Your Credentials**: After registration, you'll receive:
   - Merchant Code
   - Consumer Secret
   - API Key
   - Private Key (for secure mode - RSA key pair)

3. **Generate Private Key** (if using secure mode):
   - Generate an RSA key pair using OpenSSL:
     ```bash
     openssl genrsa -out private_key.pem 2048
     openssl rsa -in private_key.pem -pubout -out public_key.pem
     ```
   - Use the private key in PEM format for signature generation
   - Upload the public key to Jenga HQ

For more details, refer to the [Jenga Developer Documentation](https://developer.jengahq.io/guides/get-started/developer-quickstart).

### Environment Variables

Add these to your `.env` file:

```env
JENGA_ENVIRONMENT=sandbox
JENGA_MERCHANT_CODE=your_merchant_code
JENGA_CONSUMER_SECRET=your_consumer_secret
JENGA_API_KEY=your_api_key
JENGA_PRIVATE_KEY=your_private_key
JENGA_CALLBACK_URL=/kcejenga/callback
JENGA_VERIFY_HASH=false
```

### Configuration File

The configuration file will be published to `config/kcejenga.php`. You can modify it directly or use environment variables.

## Usage

### Programmatic Configuration

You can configure the package programmatically using the fluent interface:

```php
use Kce\Kcejenga\Facades\Kcejenga;

// Configure settings
Kcejenga::configure()
    ->setMerchantCode('your_merchant_code')
    ->setConsumerSecret('your_consumer_secret')
    ->setApiKey('your_api_key')
    ->setPrivateKey('your_private_key')
    ->setCallbackUrl('/custom/callback/route')
    ->setEnvironment('sandbox')
    ->save();
```

### Initiate Payment

```php
use Kce\Kcejenga\Facades\Kcejenga;

// Prepare payment data according to official Jenga API specification
// Reference: https://developer.jengahq.io/guides/jenga-pgw/checkout-reference
$paymentData = [
    'orderReference' => 'ORD12345678', // Min 8 characters, alphanumeric
    'orderAmount' => 1000.00,
    'currency' => 'KES', // Required
    'countryCode' => 'KE', // Required - Alpha-2 country code
    'customerFirstName' => 'John', // Required
    'customerLastName' => 'Doe', // Required
    'customerEmail' => 'john.doe@example.com', // Required
    'customerPhone' => '+254712345678', // Required - Format: +254 7XXXXXXXXX for Kenya
    'customerAddress' => '123 Main Street', // Required
    'customerPostalCodeZip' => '00100', // Required
    'productType' => 'Product', // Required - Product or Service
    'productDescription' => 'Payment for order ORD12345678', // Max 200 characters
    'paymentTimeLimit' => '15mins', // Required - Duration payment is valid (e.g., "15mins")
    'extraData' => 'Optional metadata', // Optional - Will be echoed back in callback
    'secondaryReference' => 'ADM1234', // Optional - Secondary reference
    'orderItems' => [ // Optional - Cart items
        [
            'itemName' => 'Product 1',
            'amount' => 500.00,
            'quantity' => 1,
        ],
        [
            'itemName' => 'Product 2',
            'amount' => 500.00,
            'quantity' => 1,
        ],
    ],
];

// Get payment data
$payment = Kcejenga::initiatePayment($paymentData);

// $payment contains:
// - payment_url: The Jenga payment gateway URL
// - form_data: All form fields needed for submission
// - method: POST

// In your view, create a form to submit to the payment gateway
```

### Payment Form Example

```blade
<form action="{{ $payment['payment_url'] }}" method="{{ $payment['method'] }}">
    @foreach($payment['form_data'] as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
    <button type="submit">Proceed to Pay</button>
</form>
```

### Handle Payment Callback

The package automatically handles callbacks at `/kcejenga/callback` according to the [official Jenga API response format](https://developer.jengahq.io/guides/jenga-pgw/checkout-reference).

**Callback Response Fields:**
- `status` - Transaction status ("paid" for successful transactions)
- `transactionId` - Jenga transaction ID
- `orderReference` - Your order reference
- `amount` - Transaction amount
- `date` - Transaction date (yyyy-MM-dd'T'HH:mm:ss.SSSz format)
- `desc` - Payment channel (CARD, EQUITEL, MPESA, AIRTEL)
- `hash` - Security hash
- `extraData` - Any metadata you sent

You can customize the success and failure URLs in your config:

```php
// In config/kcejenga.php
'success_url' => '/payment/success',
'failure_url' => '/payment/failed',
```

### Check Transaction Status

```php
use Kce\Kcejenga\Models\JengaTransaction;

// Get transaction by order reference
$transaction = JengaTransaction::byOrderReference('ORD-12345')->first();

if ($transaction && $transaction->isSuccessful()) {
    // Payment was successful
    echo "Payment successful: " . $transaction->formatted_amount;
}
```

### Using the Settings Interface

#### Via Web Routes

```bash
# View settings
GET /kcejenga/settings

# Update settings
POST /kcejenga/settings
{
    "merchant_code": "your_code",
    "consumer_secret": "your_secret",
    "api_key": "your_key",
    "environment": "sandbox",
    "callback_url": "/kcejenga/callback"
}

# Test connection
POST /kcejenga/settings/test
```

#### Via API Routes

```bash
# View settings
GET /api/kcejenga/settings

# Update settings
POST /api/kcejenga/settings

# Test connection
POST /api/kcejenga/settings/test
```

**Note:** Remember to add authentication middleware to protect settings routes in your application.

### Using Services Directly

```php
use Kce\Kcejenga\Services\JengaPaymentService;
use Kce\Kcejenga\Services\JengaSettingsService;

// Payment Service
$paymentService = app(JengaPaymentService::class);
$token = $paymentService->authenticate();
$signature = $paymentService->generateSignature('ORD-123', 'KES', 1000.00);

// Settings Service
$settingsService = app(JengaSettingsService::class);
$settings = $settingsService->getSettings();
$isValid = $settingsService->validateSettings();
```

## API Reference

### Facade Methods

#### Payment Methods

- `Kcejenga::authenticate()` - Get access token from Jenga API
- `Kcejenga::generateSignature($orderReference, $currency, $amount, $callbackUrl)` - Generate RSA signature
- `Kcejenga::initiatePayment($paymentData)` - Initiate payment and get form data
- `Kcejenga::getPaymentEndpoint()` - Get payment gateway URL

#### Settings Methods

- `Kcejenga::configure()` - Start fluent configuration
- `Kcejenga::setMerchantCode($code)` - Set merchant code
- `Kcejenga::setConsumerSecret($secret)` - Set consumer secret
- `Kcejenga::setApiKey($key)` - Set API key
- `Kcejenga::setPrivateKey($key)` - Set private key
- `Kcejenga::setEnvironment($env)` - Set environment (sandbox/production)
- `Kcejenga::setCallbackUrl($url)` - Set callback URL
- `Kcejenga::getSettings()` - Get all settings
- `Kcejenga::validateSettings()` - Validate required settings
- `Kcejenga::save()` - Save settings to config file
- `Kcejenga::testConnection()` - Test API connection

### Model Methods

#### JengaTransaction Model

- `$transaction->isSuccessful()` - Check if transaction is successful
- `$transaction->formatted_amount` - Get formatted amount
- `JengaTransaction::successful()` - Query successful transactions
- `JengaTransaction::failed()` - Query failed transactions
- `JengaTransaction::byOrderReference($ref)` - Find by order reference

## Routes

### Web Routes

- `POST /kcejenga/callback` - Payment callback handler
- `GET /kcejenga/status` - Check transaction status
- `GET /kcejenga/settings` - View settings
- `POST /kcejenga/settings` - Update settings
- `POST /kcejenga/settings/test` - Test connection

### API Routes

- `POST /api/kcejenga/callback` - Payment callback handler
- `GET /api/kcejenga/status` - Check transaction status
- `GET /api/kcejenga/settings` - View settings
- `POST /api/kcejenga/settings` - Update settings
- `POST /api/kcejenga/settings/test` - Test connection

## Database Schema

The package creates a `jenga_transactions` table with the following structure:

- `id` - Primary key
- `order_status` - Transaction status (SUCCESS, FAILED, etc.)
- `order_reference` - Order reference number
- `transaction_reference` - Jenga transaction reference
- `transaction_amount` - Transaction amount
- `transaction_currency` - Currency code
- `payment_channel` - Payment channel used
- `transaction_date` - Transaction date/time
- `created_at` - Record creation timestamp
- `updated_at` - Record update timestamp

## Error Handling

The package includes comprehensive error handling:

- Authentication errors are logged and exceptions are thrown
- Invalid configurations are validated before use
- Payment callbacks are validated and errors are logged
- All errors include detailed messages for debugging

## Security

- Sensitive data (API keys, secrets) are masked when displayed via settings interface
- Private keys are stored securely in configuration
- RSA signatures are generated for secure payment processing
- SSL verification can be configured (disabled by default for compatibility)

## Testing

### Sandbox Environment

Use the sandbox environment for testing:

```php
Kcejenga::configure()
    ->setEnvironment('sandbox')
    ->save();
```

### Test Connection

```php
$result = Kcejenga::testConnection();

if ($result['success']) {
    echo "Connection successful!";
} else {
    echo "Connection failed: " . $result['message'];
}
```

## Support

For issues, questions, or contributions, please contact:

**Koech Connect Enterprise (KCE)**  
Email: info@kcenterprise.top

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Changelog

### Version 1.0.0

- Initial release
- Payment initiation following [official Jenga API specification](https://developer.jengahq.io/guides/jenga-pgw/checkout-reference)
- Callback handling with proper field mapping (status, transactionId, desc, etc.)
- Settings management interface (web & API)
- Transaction storage
- Fluent configuration API
- Comprehensive validation:
  - Order reference validation (min 8 chars, alphanumeric)
  - Product description length validation (max 200 chars)
  - Payment time limit formatting
- Support for secondaryReference parameter
- Hash verification support for callback security
- Proper handling of official API response format

