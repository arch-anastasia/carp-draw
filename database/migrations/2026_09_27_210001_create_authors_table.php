<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('role', 160)->nullable();
            $table->text('bio')->nullable();
            $table->foreignUuid('avatar_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('website_url', 500)->nullable();
            $table->json('links')->nullable();

            $table->boolean('profile_is_published')->default(true);

            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authors');
    }
};
