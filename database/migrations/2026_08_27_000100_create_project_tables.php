<?php

use App\Enums\ProjectStatus;
use App\Enums\WorkflowMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The workspace library: room folders, and one row per workflow run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_folders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            /*
             * Deleting a folder must not delete the drawings inside it —
             * they fall back to "All projects".
             */
            $table->foreignUuid('project_folder_id')->nullable()->constrained('project_folders')->nullOnDelete();

            $table->string('title');
            $table->enum('workflow', WorkflowMode::values());
            $table->enum('status', ProjectStatus::values())->default(ProjectStatus::Queued->value);

            /* Private-disk paths. Never public URLs. */
            $table->string('source_path')->nullable();
            $table->string('source_filename')->nullable();
            $table->string('output_path')->nullable();
            $table->string('output_format', 10)->nullable();

            /* The wizard's step-2 answers (unit, dimensions, style, …). */
            $table->json('settings')->nullable();

            $table->string('error_code', 60)->nullable();
            $table->string('error_message', 500)->nullable();

            $table->string('provider', 40)->nullable();
            $table->string('provider_model', 120)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedInteger('credits_spent')->default(0);
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
        Schema::dropIfExists('project_folders');
    }
};
