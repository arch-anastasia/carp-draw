<?php

namespace App\Models\Concerns;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostSlugRedirect;
use Illuminate\Support\Str;

trait HasBlogSlug
{
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function generateUniqueSlug(string $source, ?string $excludeId = null): string
    {
        $base = Str::slug($source) ?: 'entry';
        $slug = $base;
        $i = 1;

        while (static::blogSlugTaken($slug, $excludeId)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public static function blogSlugTaken(string $slug, ?string $excludeId = null): bool
    {
        if (in_array($slug, (array) config('blog.reserved_slugs', []), true)) {
            return true;
        }

        foreach ([Post::class, Category::class] as $model) {
            $exists = $model::query()
                ->where('slug', $slug)
                ->when(
                    $excludeId && $model === static::class,
                    fn ($query) => $query->where('id', '!=', $excludeId)
                )
                ->exists();

            if ($exists) {
                return true;
            }
        }

        return PostSlugRedirect::query()->where('slug', $slug)
            ->when($excludeId && static::class === Post::class, fn ($query) => $query->where('post_id', '!=', $excludeId))
            ->exists();
    }
}
