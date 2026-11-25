<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A balance is never stored. It's paid purchases minus what generations charged, so the two can't drift apart.
        Schema::create('credit_purchases', function (Blueprint $table) {
            // Also the Idempotency-Key sent to Stripe, so a resent request never opens a second checkout.
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            // One per click on Buy, so a double click makes one purchase.
            $table->ulid('client_request_id')->unique();
            $table->string('checkout_id')->nullable()->unique();
            // Integer nanodollars ($0.000000001), like every other amount.
            $table->unsignedBigInteger('amount_nd');
            // Set once, by whichever sees the payment first: the return page or the webhook. Null means not paid.
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_ref')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_purchases');
    }
};
