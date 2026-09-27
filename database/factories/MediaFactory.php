<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /*
         * A committed asset by default - null disk, so nothing treats the row
         * as a file it may delete. Tests that need a managed upload set the
         * disk and path explicitly.
         */
        return [
            'url' => '/images/'.fake()->unique()->slug(2).'.webp',
            'disk' => null,
            'path' => null,
            'alt' => fake()->sentence(),
            'mime' => 'image/webp',
        ];
    }
}
