<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->string('excerpt', 500)->nullable();
            $table->longText('body');
            $table->foreignUuid('cover_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->string('audience')->nullable();
            $table->string('cta_text')->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->timestamp('dated_at')->nullable();

            $table->json('author_ids')->nullable();
            $table->json('category_ids')->nullable();

            $table->string('note', 500)->nullable();

            $table->json('superseded')->nullable();

            $table->timestamp('applied_at')->nullable();
            $table->foreignUuid('applied_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['post_id', 'applied_at']);
        });

        Schema::getConnection()->statement(
            'CREATE UNIQUE INDEX post_revisions_one_pending ON post_revisions (post_id) '.
            'WHERE applied_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('post_revisions');
    }
};
