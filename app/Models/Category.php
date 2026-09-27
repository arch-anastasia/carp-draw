<?php

namespace App\Models;

use App\Models\Concerns\HasBlogSlug;
use App\Models\Concerns\Retirable;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $published_posts_count
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasBlogSlug, HasFactory, HasUuids, Retirable;

    protected $fillable = [
        'name',
        'slug',
        'title',
        'description',
        'intro',
        'audience',
        'retired_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'retired_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $category): void {
            if (empty($category->slug)) {
                $category->slug = static::generateUniqueSlug((string) $category->name);
            }
        });
    }

    /**
     * @return BelongsToMany<Post, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class)->withTimestamps();
    }

    public function url(): string
    {
        return route('marketing.blog.entry', $this->slug);
    }

    public function headline(): string
    {
        return $this->title ?: $this->name;
    }
}
