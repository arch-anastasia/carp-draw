<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostRevision>
 */
class PostRevisionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'title' => fake()->sentence(5),
            'excerpt' => fake()->sentence(12),
            'body' => fake()->paragraph(),
            'author_ids' => [],
            'category_ids' => [],
        ];
    }

    public function of(Post $post): static
    {
        return $this->state(fn (): array => [
            'post_id' => $post->id,
            ...$post->only(PostRevision::WORDING),
            'author_ids' => $post->authors->pluck('id')->all(),
            'category_ids' => $post->categories->pluck('id')->all(),
        ]);
    }

    public function applied(): static
    {
        return $this->state(fn (): array => ['applied_at' => now()]);
    }
}
