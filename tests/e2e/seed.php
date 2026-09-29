<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || config('database.default') !== 'sqlite'
    || ! str_ends_with((string) config('database.connections.sqlite.database'), 'e2e.sqlite')) {
    throw new RuntimeException('E2E fixtures require testing and a dedicated e2e.sqlite database.');
}

User::factory()->create([
    'name' => 'CarpDraw QA',
    'email' => 'foundation@example.test',
    'password' => Hash::make('foundation-test-password'),
]);
Post::factory()->create([
    'title' => 'Chair dimensions guide',
    'slug' => 'chair-dimensions-guide',
    'excerpt' => 'Planning a chair with clear dimensions.',
    'body' => "# Chair dimensions\n\nA fixture used only by automated tests.\n\n## Seat height\n\nMeasure before building.",
    'meta_title' => 'Chair dimensions guide',
    'meta_description' => 'Planning a chair with clear dimensions.',
    'published_at' => '2026-09-01 10:00:00',
    'dated_at' => '2026-09-01 10:00:00',
]);
Post::factory()->draft()->create(['slug' => 'private-draft']);
echo "E2E fixtures created.\n";
