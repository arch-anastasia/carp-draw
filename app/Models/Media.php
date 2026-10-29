<?php

namespace App\Models;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory, HasUuids;

    protected $table = 'media';

    protected $fillable = [
        'url',
        'disk',
        'path',
        'alt',
        'mime',
        'width',
        'height',
        'bytes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'bytes' => 'integer',
        ];
    }

    public static function forUrl(?string $url, ?string $alt = null): ?self
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = trim($url);

        $media = static::query()->firstOrNew(['url' => $url]);

        if (! $media->exists) {
            $media->fill(self::locate($url));
        }

        if ($alt !== null && trim($alt) !== '') {
            $media->alt = trim($alt);
        }

        $media->save();

        return $media;
    }

    /**
     * Work out which disk, if any, owns a URL.
     *
     * Anything under the configured runtime image disk's own URL prefix
     * (`filesystems.runtime_images` - see App\Services\Ingest\ImageIngestor)
     * is runtime-written and therefore removable. Everything else is treated
     * as a committed asset: unmanaged, and left alone by any cleanup.
     *
     * @return array{disk: string|null, path: string|null}
     */
    private static function locate(string $url): array
    {
        $disk = (string) config('filesystems.runtime_images');
        $prefix = rtrim(Storage::disk($disk)->url(''), '/').'/';

        if (str_starts_with($url, $prefix)) {
            return ['disk' => $disk, 'path' => substr($url, strlen($prefix))];
        }

        return ['disk' => null, 'path' => null];
    }

    public function isManaged(): bool
    {
        return $this->disk !== null && $this->path !== null;
    }

    public function measure(): self
    {
        if (! $this->isManaged()) {
            return $this;
        }

        $disk = Storage::disk((string) $this->disk);
        $path = (string) $this->path;

        if (! $disk->exists($path)) {
            return $this;
        }

        $contents = (string) $disk->get($path);
        $size = @getimagesizefromstring($contents);

        $this->forceFill([
            'bytes' => strlen($contents),
            'mime' => is_array($size) ? $size['mime'] : null,
            'width' => is_array($size) ? (int) $size[0] : null,
            'height' => is_array($size) ? (int) $size[1] : null,
        ])->save();

        return $this;
    }

    /**
     * Rows nothing points at any more.
     *
     * Every table that references media does so through a nullable foreign key,
     * so an orphan is a row with no referrer. This is the join that
     * `blog:orphans` used to do by hand, and it only became expressible once
     * media had a table of its own.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrphaned(Builder $query): Builder
    {
        return $query
            ->whereDoesntHave('posts')
            ->whereDoesntHave('authors');
    }

    /** @return HasMany<Post, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'cover_media_id');
    }

    /** @return HasMany<Author, $this> */
    public function authors(): HasMany
    {
        return $this->hasMany(Author::class, 'avatar_media_id');
    }
}
