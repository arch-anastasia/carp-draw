<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Enums\WorkflowMode;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->words(3, true),
            'workflow' => WorkflowMode::Drawing,
            'status' => ProjectStatus::Queued,
            'source_path' => 'drawings/test/source.jpg',
            'source_filename' => 'source.jpg',
            'settings' => ['unit' => 'mm', 'width' => '2000', 'height' => '850', 'style' => 'clean orthographic set'],
            'credits_spent' => 4,
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::Succeeded,
            'output_path' => 'drawings/test/output.webp',
            'output_format' => 'webp',
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::Failed,
            'error_code' => 'provider_rejected',
            'error_message' => 'That generation did not complete. Your credits are untouched.',
            'completed_at' => now(),
        ]);
    }
}
