<?php

use App\Enums\CreditTransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The credit ledger: append-only, signed, and the only place a balance is
 * kept.
 *
 * balance = SUM(amount). That is the whole rule, and it is why `amount` is
 * signed and why there is no period column to filter on: rolling a subscriber
 * over inserts a Grant for exactly the shortfall, so the all-time sum lands
 * back on the allowance. Every movement says why it happened and which
 * project caused it.
 *
 * `balance_after` is written at insert time inside the same locked
 * transaction that computes it. Redundant by construction, which is the
 * point: it is the audit trail's own checksum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            $table->enum('type', CreditTransactionType::values());

            /* Signed. A spend is negative, a grant is positive. */
            $table->integer('amount');

            /* The running balance immediately after this row. */
            $table->integer('balance_after');

            /*
             * What caused it. Kept when the cause goes: a credit movement is
             * an accounting fact and outlives the drawing it was spent on.
             */
            $table->foreignUuid('project_id')->nullable()->constrained()->nullOnDelete();

            /* Human-readable reason, for the operator and for support. */
            $table->string('description')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
