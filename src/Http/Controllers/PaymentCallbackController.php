<?php

namespace Kce\Kcejenga\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Kce\Kcejenga\Models\JengaTransaction;

class PaymentCallbackController extends Controller
{
    /**
     * Handle payment callback from Jenga gateway
     *
     * @param Request $request
     * @return JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request)
    {
        try {
            // Get transaction data from request (official Jenga API response format)
            // Reference: https://developer.jengahq.io/guides/jenga-pgw/checkout-reference
            $status = $request->input('status'); // "paid" for successful transactions
            $orderReference = $request->input('orderReference');
            $transactionId = $request->input('transactionId');
            $amount = $request->input('amount');
            $date = $request->input('date'); // Format: yyyy-MM-dd'T'HH:mm:ss.SSSz
            $desc = $request->input('desc'); // Payment channel: CARD, EQUITEL, MPESA, AIRTEL
            $hash = $request->input('hash'); // Security hash
            $extraData = $request->input('extraData', '');

            // Validate required fields according to official API
            if (empty($status) || empty($orderReference)) {
                Log::warning('Jenga callback missing required fields', $request->all());
                
                return $request->expectsJson() 
                    ? response()->json(['success' => false, 'message' => 'Missing required fields'], 400)
                    : redirect()->back()->with('error', 'Invalid callback data');
            }

            // Verify hash if configured (security enhancement)
            if (!empty($hash) && config('kcejenga.verify_hash', false)) {
                // Hash verification logic can be added here if Jenga provides hash generation details
                // For now, we log it for reference
                Log::info('Jenga callback hash received', ['hash' => $hash]);
            }

            // Parse date format: yyyy-MM-dd'T'HH:mm:ss.SSSz
            $transactionDate = null;
            if (!empty($date)) {
                try {
                    // Try to parse the ISO 8601 format
                    $transactionDate = \Carbon\Carbon::parse($date);
                } catch (\Exception $e) {
                    Log::warning('Failed to parse transaction date', ['date' => $date]);
                    $transactionDate = now();
                }
            } else {
                $transactionDate = now();
            }

            // Prepare transaction data (mapping official API fields to our database)
            $transactionData = [
                'order_status' => strtoupper($status) === 'PAID' ? 'SUCCESS' : strtoupper($status),
                'order_reference' => $orderReference,
                'transaction_reference' => $transactionId ?? '',
                'transaction_amount' => $amount ?? '0.00',
                'transaction_currency' => '', // Not provided in callback, can be stored from original payment
                'payment_channel' => $desc ?? '', // CARD, EQUITEL, MPESA, AIRTEL
                'transaction_date' => $transactionDate,
            ];

            // Store transaction record
            try {
                JengaTransaction::create($transactionData);
            } catch (\Exception $e) {
                Log::error('Failed to store Jenga transaction', [
                    'error' => $e->getMessage(),
                    'data' => $transactionData,
                ]);
            }

            // Handle success case (official API returns "paid" for successful transactions)
            if (strtoupper($status) === 'PAID') {
                // Fire event for successful payment (can be extended)
                // event(new PaymentSuccessful($orderReference, $transactionData));

                Log::info('Jenga payment successful', [
                    'order_reference' => $orderReference,
                    'transaction_id' => $transactionId,
                    'amount' => $amount,
                    'channel' => $desc,
                ]);

                // Return response based on request type
                if ($request->expectsJson() || $request->wantsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Payment successful',
                        'transaction' => $transactionData,
                    ]);
                }

                // For web requests, redirect to success URL
                // You can configure this in config or use a default
                $successUrl = config('kcejenga.success_url', '/payment/success');
                
                return redirect($successUrl . '?' . http_build_query([
                    'status' => 'success',
                    'orderReference' => $orderReference,
                    'transactionId' => $transactionId,
                    'amount' => $amount,
                    'channel' => $desc,
                ]));
            }

            // Handle failed/cancelled cases
            Log::warning('Jenga payment failed', [
                'order_reference' => $orderReference,
                'status' => $status,
                'transaction_id' => $transactionId,
            ]);

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment status: ' . $status,
                    'transaction' => $transactionData,
                ], 400);
            }

            // Redirect to failure URL
            $failureUrl = config('kcejenga.failure_url', '/payment/failed');
            
            return redirect($failureUrl . '?' . http_build_query([
                'status' => 'failed',
                'orderReference' => $orderReference,
                'transactionStatus' => $status,
            ]))->with('error', 'Payment status: ' . $status);

        } catch (\Exception $e) {
            Log::error('Jenga callback error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error processing callback: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()->with('error', 'Error processing payment callback');
        }
    }

    /**
     * Get transaction status
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function status(Request $request)
    {
        $orderReference = $request->input('order_reference');
        
        if (empty($orderReference)) {
            return response()->json([
                'success' => false,
                'message' => 'Order reference is required',
            ], 400);
        }

        $transaction = JengaTransaction::where('order_reference', $orderReference)
            ->orderBy('transaction_date', 'desc')
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'transaction' => $transaction->toArray(),
        ]);
    }
}

