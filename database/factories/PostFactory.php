<?php

namespace Database\Factories;

use App\Models\Author;
use App\Models\Media;
use App\Models\Post;
use App\Services\HouseAuthor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Post $post): void {
            if ($post->authors()->doesntExist()) {
                $post->authors()->attach(app(HouseAuthor::class)->resolve()->id, ['sort_order' => 0]);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->sentence(5));

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
            'excerpt' => fake()->sentence(14),
            'body' => $this->body(),
            'cover_media_id' => null,
            'meta_title' => $title,
            'meta_description' => fake()->sentence(12),
            'audience' => null,
            'cta_text' => null,
            'cta_url' => null,
            /*
             * Minutes old, not months: the seeded launch catalog is dated,
             * the listing paginates by nine, and a factory post backdated a
             * year would be tested for on page three.
             */
            'published_at' => now()->subMinutes(fake()->numberBetween(1, 600)),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['published_at' => null]);
    }

    public function killed(?string $reason = null): static
    {
        return $this->state(fn (): array => [
            'killed_at' => now(),
            'killed_reason' => $reason,
            'published_at' => null,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => ['published_at' => now()->addWeek()]);
    }

    public function publishedAt(string $date): static
    {
        return $this->state(fn (): array => ['published_at' => $date]);
    }

    public function dated(string $date): static
    {
        return $this->state(fn (): array => ['dated_at' => $date]);
    }

    public function by(Author ...$authors): static
    {
        return $this->afterCreating(function (Post $post) use ($authors): void {
            $post->authors()->sync(collect($authors)
                ->mapWithKeys(fn (Author $author, int $index): array => [$author->id => ['sort_order' => $index]])
                ->all());
        });
    }

    public function withCover(?string $url): static
    {
        return $this->state(fn () => [
            'cover_media_id' => $url === null ? null : Media::forUrl($url)?->id,
        ]);
    }

    public function forAudience(string $audience): static
    {
        return $this->state(fn (): array => [
            'audience' => $audience,
        ]);
    }

    public function demoListicle(int $entries = 55): static
    {
        $body = [
            ':::section intro',
            'DEMO CONTENT — not editorial. This fixture exercises the long-form template.',
            ':::endsection',
            ':::section core',
        ];

        for ($index = 1; $index <= $entries; $index++) {
            $body[] = '## '.$index.'. Demo hairstyle '.$index.' {.style-entry #demo-style-'.$index.'}';
            $body[] = 'Synthetic description for performance testing only. [See the related guide](/blog/demo-related).';

            if ($index === 2) {
                $body[] = ":::gallery\n![Fake front view](/images/demo/ada-pixie.webp \"Front\")\n![Fake side view](/images/demo/noah-buzz-cut.webp \"Side\")\n:::";
            }

            if ($index === 3) {
                $body[] = ':::try-on Preview this demo entry on your own photo. :::';
            }

            if ($index === 4) {
                $body[] = ':::youtube dQw4w9WgXcQ "Demo styling video" :::';
            }
        }

        $body[] = ':::endsection';
        $body[] = "| Attribute | Demo value |\n| --- | --- |\n| Upkeep | Synthetic |";
        $body[] = ":::section conclusion\nDemo conclusion.\n:::endsection";
        $body[] = "## FAQ\n### Is this real editorial content?\nNo. It is a local, draft-only test fixture.\n\n### Can it be published?\nNot by this factory state.";

        return $this->draft()->state(fn (): array => [
            'title' => 'DEMO: 55 fake hairstyles',
            'body' => implode("\n\n", $body),
            'excerpt' => 'DEMO CONTENT — template stress fixture.',
        ]);
    }

    private function body(): string
    {
        return implode("\n\n", [
            fake()->paragraph(),
            '## '.Str::title(fake()->words(3, true)),
            fake()->paragraph(),
            '- '.fake()->sentence(),
            '- '.fake()->sentence(),
        ]);
    }
}
