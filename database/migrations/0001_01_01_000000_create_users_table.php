<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The account, and the two framework tables that hang off it.
 *
 * Keys: every table the APPLICATION owns is keyed by a UUIDv7 (Laravel's
 * HasUuids), so an id is unguessable in a URL and still sorts by creation
 * time in an index. The framework's own tables keep whatever key the
 * framework inserts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name')->default('');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();

            /*
             * A social-only or passwordless account still gets a random
             * password so the column is never null; `has_password` is what
             * says whether the person knows it, and therefore whether to
             * offer "change" or "set".
             */
            $table->string('password');
            $table->boolean('has_password')->default(false);

            $table->string('social_provider')->nullable();
            $table->string('social_id')->nullable();

            $table->boolean('is_admin')->default(false);

            // Interface language preference ('en' | 'ru').
            $table->string('language', 5)->default('en');

            /*
             * When the current credit window opened. The ledger holds the
             * movements; this is the clock that decides when a subscriber is
             * topped back up. Null for an account that has never generated.
             */
            $table->timestamp('credits_period_started_at')->nullable();

            /* Cashier. */
            $table->string('stripe_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();

            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();

            /*
             * One account per social identity: a plain index would let a
             * retried OAuth callback create a second account for the same
             * Google user. Nulls do not collide, so password accounts are
             * unaffected.
             */
            $table->unique(['social_provider', 'social_id']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
