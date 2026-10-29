<?php

/** Prove delayed publication survives a real Redis queue/worker round trip. */
use App\Models\Post;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || config('database.default') !== 'pgsql'
    || ! in_array(config('database.connections.pgsql.database'), ['carpdraw_foundation_test', 'carpdraw_ci'], true)) {
    throw new RuntimeException('Queue check requires the dedicated PostgreSQL test database.');
}
$queue = 'foundation-check-'.Str::uuid();
config([
    'queue.default' => 'redis',
    'queue.connections.redis.queue' => $queue,
    'queue.connections.redis.block_for' => null,
    'blog.indexnow.enabled' => true,
    'blog.indexnow.key' => 'queue-test-only',
]);
Http::preventStrayRequests();
Http::fake(['api.indexnow.org/*' => Http::response([], 200)]);
$post = null;
try {
    $post = Post::factory()->create(['published_at' => now()->addMinute()]);
    $connection = Queue::connection('redis');
    if ($connection->size($queue) !== 1 || $connection->pop($queue) !== null) {
        throw new RuntimeException('Scheduled publication was not held in the delayed queue.');
    }
    Http::assertNothingSent();
    Carbon::setTestNow($post->published_at->copy()->addSecond());
    Artisan::call('queue:work', ['connection' => 'redis', '--queue' => $queue, '--once' => true, '--sleep' => 0, '--tries' => 1]);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => $request['urlList'] === [$post->url()]);
    if ($connection->size($queue) !== 0) {
        throw new RuntimeException('The publication job was not acknowledged.');
    }
    echo "Redis: publication stayed delayed, worker announced it once, queue drained. No external request sent.\n";
} finally {
    Carbon::setTestNow();
    Queue::connection('redis')->clear($queue);
    $post?->delete();
}
