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

            // Check for duplicate transaction
            $existingTransaction = null;
            if (!empty($transactionId)) {
                $existingTransaction = JengaTransaction::where('transaction_reference', $transactionId)->first();
            }
            if (!$existingTransaction && !empty($orderReference)) {
                $existingTransaction = JengaTransaction::where('order_reference', $orderReference)
                    ->where('order_status', 'SUCCESS')
                    ->first();
            }

            if ($existingTransaction) {
                Log::info('Duplicate Jenga callback received', [
                    'order_reference' => $orderReference,
                    'transaction_id' => $transactionId,
                ]);
                
                // Still try to complete payment if it exists and is not completed
                $this->completePayment($orderReference, $transactionId, $status, $request->all());
                
                // Return success to prevent retry loops
                return $request->expectsJson() 
                    ? response()->json(['success' => true, 'message' => 'Transaction already processed'])
                    : redirect(config('kcejenga.success_url', '/payment/success'));
            }

            // Store transaction record
            try {
                JengaTransaction::create($transactionData);
            } catch (\Exception $e) {
                Log::error('Failed to store Jenga transaction', [
                    'error' => $e->getMessage(),
                    'data' => $transactionData,
                ]);
                // Don't throw - callback should still succeed
            }

            // Complete payment transaction if status is "paid"
            $paymentCompleted = false;
            if (strtoupper($status) === 'PAID') {
                $paymentCompleted = $this->completePayment($orderReference, $transactionId, $status, $request->all());
            }

            // Handle success case (official API returns "paid" for successful transactions)
            if (strtoupper($status) === 'PAID') {
                Log::info('Jenga payment successful', [
                    'order_reference' => $orderReference,
                    'transaction_id' => $transactionId,
                    'amount' => $amount,
                    'channel' => $desc,
                    'payment_completed' => $paymentCompleted,
                ]);

                // Return response based on request type
                if ($request->expectsJson() || $request->wantsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Payment successful',
                        'transaction' => $transactionData,
                        'payment_completed' => $paymentCompleted,
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

            // Mark payment as failed if it exists
            $this->markPaymentAsFailed($orderReference, $transactionId, $status, $request->all());

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
     * Mark payment as failed
     * 
     * @param string $orderReference
     * @param string|null $transactionId
     * @param string $status
     * @param array $callbackData
     * @return bool
     */
    protected function markPaymentAsFailed(string $orderReference, ?string $transactionId, string $status, array $callbackData): bool
    {
        try {
            // Check if Payment model exists (from main application)
            if (!class_exists(\App\Models\Payment::class)) {
                return false;
            }

            $payment = \App\Models\Payment::where('transaction_id', $orderReference)
                ->orWhere('reference_number', $orderReference)
                ->orWhere('reference_number', $transactionId)
                ->first();

            if (!$payment) {
                return false;
            }

            // Check if payment is already in a final state
            if (in_array($payment->status, ['completed', 'failed', 'cancelled'])) {
                return true;
            }

            // Prepare provider response data
            $providerResponse = [
                'jenga_callback' => $callbackData,
                'jenga_transaction_id' => $transactionId,
                'jenga_status' => $status,
                'jenga_payment_channel' => $callbackData['desc'] ?? null,
                'jenga_callback_received_at' => now()->toIso8601String(),
            ];

            // Mark payment as failed
            if (method_exists($payment, 'markAsFailed')) {
                $payment->markAsFailed('Payment status: ' . $status, $providerResponse);
            } else {
                // Fallback: update payment directly
                $payment->update([
                    'status' => 'failed',
                    'provider_response' => array_merge($payment->provider_response ?? [], $providerResponse),
                ]);
            }

            Log::info('Payment marked as failed via Jenga callback', [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'status' => $status,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to mark payment as failed via Jenga callback', [
                'error' => $e->getMessage(),
                'order_reference' => $orderReference,
            ]);
            return false;
        }
    }

    /**
     * Complete payment transaction
     * 
     * This method attempts to find and complete the payment record in the main application.
     * The orderReference is typically the transaction_id of the Payment model.
     *
     * @param string $orderReference
     * @param string|null $transactionId
     * @param string $status
     * @param array $callbackData
     * @return bool
     */
    protected function completePayment(string $orderReference, ?string $transactionId, string $status, array $callbackData): bool
    {
        try {
            // Check if Payment model exists (from main application)
            if (!class_exists(\App\Models\Payment::class)) {
                Log::debug('Payment model not found, skipping payment completion');
                return false;
            }

            $payment = \App\Models\Payment::where('transaction_id', $orderReference)
                ->orWhere('reference_number', $orderReference)
                ->orWhere('reference_number', $transactionId)
                ->first();

            if (!$payment) {
                Log::warning('Payment not found for order reference', [
                    'order_reference' => $orderReference,
                    'transaction_id' => $transactionId,
                ]);
                return false;
            }

            // Check if payment is already completed
            if ($payment->status === 'completed') {
                Log::info('Payment already completed', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                ]);
                return true;
            }

            // Prepare provider response data
            $providerResponse = [
                'jenga_callback' => $callbackData,
                'jenga_transaction_id' => $transactionId,
                'jenga_status' => $status,
                'jenga_payment_channel' => $callbackData['desc'] ?? null,
                'jenga_callback_received_at' => now()->toIso8601String(),
            ];

            // Mark payment as completed
            if (method_exists($payment, 'markAsCompleted')) {
                $payment->markAsCompleted($transactionId ?? $orderReference, $providerResponse);
                
                Log::info('Payment marked as completed via Jenga callback', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'jenga_transaction_id' => $transactionId,
                    'order_reference' => $orderReference,
                ]);
                
                return true;
            } else {
                // Fallback: update payment directly if markAsCompleted doesn't exist
                $payment->update([
                    'status' => 'completed',
                    'reference_number' => $transactionId ?? $payment->reference_number ?? $orderReference,
                    'provider_response' => array_merge($payment->provider_response ?? [], $providerResponse),
                    'completed_at' => now(),
                ]);
                
                Log::info('Payment updated to completed via Jenga callback (fallback)', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                ]);
                
                return true;
            }
        } catch (\Exception $e) {
            Log::error('Failed to complete payment via Jenga callback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'order_reference' => $orderReference,
                'transaction_id' => $transactionId,
            ]);
            return false;
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

        // Also check payment status if Payment model exists
        $paymentStatus = null;
        if (class_exists(\App\Models\Payment::class)) {
            $payment = \App\Models\Payment::where('transaction_id', $orderReference)
                ->orWhere('reference_number', $orderReference)
                ->first();
            
            if ($payment) {
                $paymentStatus = [
                    'id' => $payment->id,
                    'status' => $payment->status,
                    'amount' => $payment->amount,
                    'completed_at' => $payment->completed_at?->toIso8601String(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'transaction' => $transaction->toArray(),
            'payment' => $paymentStatus,
        ]);
    }
}

