<?php

namespace App\Jobs;

use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SubmitPostToIndexNow implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $url) {}

    public function handle(): void
    {
        $key = (string) config('blog.indexnow.key');

        if ($key === '') {
            return;
        }

        $slug = rawurldecode(basename((string) parse_url($this->url, PHP_URL_PATH)));

        if (Post::query()->killed()->where('slug', $slug)->exists()) {
            return;
        }

        try {
            Http::timeout(10)->post('https://api.indexnow.org/indexnow', [
                'host' => parse_url($this->url, PHP_URL_HOST),
                'key' => $key,
                'urlList' => [$this->url],
            ])->throw();
        } catch (Throwable $exception) {
            /* Search-engine notification is never part of the write's success. */
            Log::warning('IndexNow submission failed.', [
                'url' => $this->url,
                'exception' => $exception,
            ]);
        }
    }
}
