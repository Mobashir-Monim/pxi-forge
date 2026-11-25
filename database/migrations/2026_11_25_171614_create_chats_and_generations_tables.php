<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('active');
            $table->string('archived_reason')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('generations', function (Blueprint $table) {
            // Also the Idempotency-Key sent to Pixelforge, so resubmitting this row never creates a second job there.
            $table->ulid('id')->primary();
            $table->foreignUlid('chat_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            // Unique: a failed generation is retried at most once, so a double-clicked retry can't create two.
            $table->ulid('retry_of_id')->nullable()->unique();
            // One per click on Send, so a double submit can't create two.
            $table->ulid('client_request_id')->nullable()->unique();
            $table->string('provider_id')->nullable()->unique();
            $table->string('status')->index();

            // What the user typed, and what Pixelforge receives: the message with every reference note appended.
            $table->text('message');
            $table->text('prompt');
            $table->string('size');
            $table->string('quality');
            $table->jsonb('references');

            // Money is integer nanodollars ($0.000000001). The estimate is the user's price range, markup included.
            $table->unsignedBigInteger('estimate_min_nd');
            $table->unsignedBigInteger('estimate_max_nd');
            // Rates fixed by Pixelforge when it accepted the job, USD per 1M tokens as exact decimal strings.
            $table->jsonb('rates')->nullable();
            $table->unsignedInteger('input_text_tokens')->nullable();
            $table->unsignedInteger('input_image_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('reasoning_tokens')->nullable();
            // Set once the generation reaches a final state: what Pixelforge charged us, and what we charged the user.
            $table->unsignedBigInteger('provider_cost_nd')->nullable();
            $table->unsignedBigInteger('charge_nd')->nullable();

            // While pending: why it hasn't reached Pixelforge yet, and how often we've tried.
            $table->string('waiting_reason')->nullable();
            $table->unsignedSmallInteger('submit_attempts')->default(0);
            $table->string('failure_reason')->nullable();
            $table->text('error_message')->nullable();

            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();
            $table->string('image_path')->nullable();

            // Pixelforge's clock. completed_at decides the UTC day a generation is billed on, as GET /v1/usage counts it.
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamp('timed_out_at')->nullable();
            $table->timestamps();

            // A chat runs one generation at a time. The database enforces it, so two racing sends can't both get through.
            // The column holds chat_id only while the generation is active, and a unique index ignores nulls. This works on
            // MySQL and Postgres alike, where a partial index would only work on Postgres.
            $table->char('active_chat_id', 26)->nullable()->unique()
                ->storedAs("case when status in ('pending', 'queued', 'processing') then chat_id end");

            $table->index(['chat_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        // Added after the table exists, since a key can't point at its own table's primary key before that's created.
        Schema::table('generations', function (Blueprint $table) {
            $table->foreign('retry_of_id')->references('id')->on('generations')->nullOnDelete();
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            // The provider's event id. Unique, so an event delivered twice is recorded and applied once.
            $table->string('id')->primary();
            $table->string('provider_id')->index();
            $table->string('status');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('generations');
        Schema::dropIfExists('chats');
    }
};
