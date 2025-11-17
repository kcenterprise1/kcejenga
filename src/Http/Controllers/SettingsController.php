<?php

namespace Kce\Kcejenga\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Kce\Kcejenga\Services\JengaSettingsService;
use Exception;

class SettingsController extends Controller
{
    protected $settingsService;

    public function __construct(JengaSettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Display current settings
     *
     * @param Request $request
     * @return JsonResponse|\Illuminate\View\View
     */
    public function show(Request $request)
    {
        $settings = $this->settingsService->getSettings();

        // Mask sensitive data for display
        $displaySettings = $this->maskSensitiveData($settings);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'settings' => $displaySettings,
            ]);
        }

        // For web view, return JSON (can be extended to return a view)
        return response()->json([
            'success' => true,
            'settings' => $displaySettings,
            'message' => 'Use POST /kcejenga/settings to update settings',
        ]);
    }

    /**
     * Update settings
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request)
    {
        try {
            $data = $request->all();

            // Update settings using fluent interface
            $settingsService = JengaSettingsService::configure();

            if (isset($data['merchant_code'])) {
                $settingsService->setMerchantCode($data['merchant_code']);
            }
            if (isset($data['consumer_secret'])) {
                $settingsService->setConsumerSecret($data['consumer_secret']);
            }
            if (isset($data['api_key'])) {
                $settingsService->setApiKey($data['api_key']);
            }
            if (isset($data['private_key'])) {
                $settingsService->setPrivateKey($data['private_key']);
            }
            if (isset($data['environment'])) {
                $settingsService->setEnvironment($data['environment']);
            }
            if (isset($data['callback_url'])) {
                $settingsService->setCallbackUrl($data['callback_url']);
            }

            // Save settings
            $settingsService->save();

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'settings' => $this->maskSensitiveData($settingsService->getSettings()),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Test connection with current settings
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function testConnection(Request $request)
    {
        try {
            $result = $this->settingsService->testConnection();

            $statusCode = $result['success'] ? 200 : 400;

            return response()->json($result, $statusCode);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Mask sensitive data for display
     *
     * @param array $settings
     * @return array
     */
    protected function maskSensitiveData(array $settings)
    {
        $masked = $settings;

        // Mask consumer secret (show last 4 characters)
        if (!empty($masked['consumer_secret'])) {
            $masked['consumer_secret'] = str_repeat('*', max(0, strlen($masked['consumer_secret']) - 4)) 
                . substr($masked['consumer_secret'], -4);
        }

        // Mask API key (show first 4 and last 4 characters)
        if (!empty($masked['api_key'])) {
            $length = strlen($masked['api_key']);
            if ($length > 8) {
                $masked['api_key'] = substr($masked['api_key'], 0, 4) 
                    . str_repeat('*', $length - 8) 
                    . substr($masked['api_key'], -4);
            } else {
                $masked['api_key'] = str_repeat('*', $length);
            }
        }

        // Mask private key (show only if exists, not the actual value)
        if (!empty($masked['private_key'])) {
            $masked['private_key'] = '***PRIVATE_KEY_SET***';
        }

        return $masked;
    }
}

