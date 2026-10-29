<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'title' => $name.' Guides',
            'description' => fake()->sentence(12),
            'audience' => null,
        ];
    }

    public function forAudience(string $audience): static
    {
        return $this->state(fn (): array => [
            'audience' => $audience,
        ]);
    }
}
