<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jenga_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('order_status', 150)->nullable();
            $table->string('order_reference', 150)->nullable()->index();
            $table->string('transaction_reference', 150)->nullable()->index();
            
            // Unique constraint to prevent duplicate transactions
            $table->unique(['order_reference', 'transaction_reference'], 'unique_jenga_transaction');
            $table->string('transaction_amount', 150)->nullable();
            $table->string('transaction_currency', 150)->nullable();
            $table->string('payment_channel', 150)->nullable();
            $table->dateTime('transaction_date')->nullable();
            $table->timestamps();

            // Indexes for better query performance
            $table->index('transaction_date');
            $table->index('order_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jenga_transactions');
    }
};

