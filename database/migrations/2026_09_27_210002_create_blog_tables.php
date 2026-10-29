<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('intro')->nullable();
            $table->string('audience')->nullable();

            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

        });

        Schema::create('posts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('title');
            $table->string('slug')->unique();
            $table->string('target_keyword')->nullable();
            $table->json('secondary_keywords')->nullable();
            $table->string('excerpt', 500)->nullable();
            $table->longText('body');

            $table->foreignUuid('cover_media_id')->nullable()->constrained('media')->nullOnDelete();

            $table->string('meta_title')->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->string('audience')->nullable();

            $table->string('cta_text')->nullable();
            $table->string('cta_url', 500)->nullable();

            $table->unsignedInteger('reading_minutes')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);

            $table->timestamp('published_at')->nullable();
            $table->timestamp('dated_at')->nullable();
            $table->timestamp('killed_at')->nullable();
            $table->string('killed_reason')->nullable();
            $table->timestamps();

            $table->index('published_at');
        });

        Schema::create('category_post', function (Blueprint $table) {
            $table->foreignUuid('category_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('post_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['category_id', 'post_id']);
        });

        Schema::create('author_post', function (Blueprint $table) {
            $table->foreignUuid('post_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('author_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->primary(['post_id', 'author_id']);
            $table->index('author_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('author_post');
        Schema::dropIfExists('category_post');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('categories');
    }
};
