<?php

namespace Kce\Kcejenga\Models;

use Illuminate\Database\Eloquent\Model;

class JengaTransaction extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'jenga_transactions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_status',
        'order_reference',
        'transaction_reference',
        'transaction_amount',
        'transaction_currency',
        'payment_channel',
        'transaction_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'transaction_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Check if transaction is successful
     *
     * @return bool
     */
    public function isSuccessful()
    {
        return strtoupper($this->order_status) === 'SUCCESS';
    }

    /**
     * Get formatted transaction amount
     *
     * @return string
     */
    public function getFormattedAmountAttribute()
    {
        if (empty($this->transaction_amount)) {
            return '0.00';
        }

        return number_format((float) $this->transaction_amount, 2, '.', '');
    }

    /**
     * Scope to get successful transactions
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSuccessful($query)
    {
        return $query->where('order_status', 'SUCCESS');
    }

    /**
     * Scope to get failed transactions
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFailed($query)
    {
        return $query->where('order_status', '!=', 'SUCCESS');
    }

    /**
     * Scope to get transactions by order reference
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $orderReference
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByOrderReference($query, $orderReference)
    {
        return $query->where('order_reference', $orderReference);
    }
}

