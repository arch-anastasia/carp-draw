<?php

namespace App\Console\Commands;

use App\Jobs\SubmitPostToIndexNow;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class SubmitBlogToIndexNowCommand extends Command
{
    /** @var string */
    protected $signature = 'blog:indexnow {slugs?* : Published post slugs to submit} {--dry-run : Print URLs without dispatching jobs}';

    /** @var string */
    protected $description = 'Queue published blog posts for IndexNow submission';

    public function handle(): int
    {
        $slugs = array_values(array_map('strval', (array) $this->argument('slugs')));
        $isDryRun = (bool) $this->option('dry-run');

        if (! $isDryRun && ! config('blog.indexnow.enabled')) {
            $this->error('INDEXNOW_KEY is not set. No URLs were queued.');

            return self::FAILURE;
        }

        $posts = $this->publishedPosts($slugs);

        if ($slugs !== [] && $posts->count() !== count($slugs)) {
            $foundSlugs = $posts->pluck('slug')->all();
            $invalidSlugs = array_values(array_diff($slugs, $foundSlugs));

            foreach ($invalidSlugs as $invalidSlug) {
                $this->error("Post [{$invalidSlug}] is unknown or unpublished.");
            }

            return self::FAILURE;
        }

        /*
         * Validate the complete explicit list before dispatching. A typo in a
         * deploy command must not leave a backfill half queued and make the
         * retry submit the valid half twice.
         */
        foreach ($posts as $post) {
            $url = $post->url();

            if ($isDryRun) {
                $this->line($url);
            } else {
                SubmitPostToIndexNow::dispatch($url);
                $this->line('Queued '.$url);
            }
        }

        $verb = $isDryRun ? 'Would queue' : 'Queued';
        $this->info($verb.' '.$posts->count().' URL(s).');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $slugs
     * @return Collection<int, Post>
     */
    private function publishedPosts(array $slugs): Collection
    {
        $query = Post::query()->published();

        if ($slugs === []) {
            return $query->orderBy('published_at')->get();
        }

        $postsBySlug = $query->whereIn('slug', $slugs)->get()->keyBy('slug');

        return new Collection(array_values(array_filter(
            array_map(fn (string $slug): ?Post => $postsBySlug->get($slug), $slugs),
        )));
    }
}
