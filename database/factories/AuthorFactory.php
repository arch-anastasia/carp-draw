<?php

namespace Database\Factories;

use App\Models\Author;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Author> */
class AuthorFactory extends Factory
{
    protected $model = Author::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'name' => $name,
            'username' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 9999),
            'role' => fake()->jobTitle(),
            'bio' => fake()->paragraph(),
            'profile_is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (): array => ['profile_is_published' => false]);
    }
}
