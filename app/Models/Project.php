<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\WorkflowMode;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One workflow run and its output — the unit the projects library shows.
 *
 * The uploaded source and the generated output live on the private drawing
 * disk (`drawing.provider.disk`), under drawings/{user_id}/{project_id}/.
 * Serving goes through a presigned URL when the disk can sign, otherwise
 * through the ownership-checked streaming route.
 *
 * @property string $id
 * @property string $user_id
 * @property string|null $project_folder_id
 * @property string $title
 * @property WorkflowMode $workflow
 * @property ProjectStatus $status
 * @property string|null $source_path
 * @property string|null $source_filename
 * @property string|null $output_path
 * @property string|null $output_format
 * @property array<string, mixed>|null $settings
 * @property string|null $error_code
 * @property string|null $error_message
 * @property string|null $provider
 * @property string|null $provider_model
 * @property int $attempts
 * @property int $credits_spent
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'project_folder_id',
        'title',
        'workflow',
        'status',
        'source_path',
        'source_filename',
        'output_path',
        'output_format',
        'settings',
        'error_code',
        'error_message',
        'provider',
        'provider_model',
        'attempts',
        'credits_spent',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'workflow' => WorkflowMode::class,
            'status' => ProjectStatus::class,
            'settings' => 'array',
            'attempts' => 'integer',
            'credits_spent' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ProjectFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(ProjectFolder::class, 'project_folder_id');
    }

    /**
     * The disk this project's files live on.
     */
    public function disk(): string
    {
        return (string) config('drawing.provider.disk');
    }

    /**
     * A browser-loadable URL for the generated output, or null while there
     * is none. Presigned when the disk supports it (R2/S3); the local disk
     * cannot sign, so it falls back to the streaming route, which checks
     * ownership.
     */
    public function outputUrl(): ?string
    {
        if (! $this->output_path) {
            return null;
        }

        $disk = Storage::disk($this->disk());

        try {
            return $disk->temporaryUrl($this->output_path, now()->addMinutes(30));
        } catch (\Throwable) {
            return route('projects.image', $this);
        }
    }

    /**
     * Delete every stored file this project owns. Called before the row goes
     * (account deletion, project deletion) — the DB cascade never reaches
     * the disk on its own.
     */
    public function purgeFiles(): void
    {
        $disk = Storage::disk($this->disk());

        foreach ([$this->source_path, $this->output_path] as $path) {
            if ($path) {
                try {
                    $disk->delete($path);
                } catch (\Throwable) {
                    // A missing file is already the state we want.
                }
            }
        }
    }

    /**
     * The card subtitle's time half ("Updated today").
     */
    public function updatedHuman(): string
    {
        return $this->updated_at?->diffForHumans() ?? '';
    }
}
