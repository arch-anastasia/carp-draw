<?php

namespace App\Models;

use Database\Factories\ProjectFolderFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A room folder in the project library ("Kitchen", "Home office", …).
 *
 * The four default rooms are created with the account so the library never
 * opens empty — see User::booted().
 */
class ProjectFolder extends Model
{
    /** @use HasFactory<ProjectFolderFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'name',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
