<?php

/** Independent workers against PostgreSQL: no SQLite lock or test transaction can mask a race. */
use App\Enums\WorkflowMode;
use App\Models\Project;
use App\Models\User;
use App\Services\Drawing\DrawingFailed;
use App\Services\Drawing\ProjectService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || config('database.default') !== 'pgsql'
    || ! in_array(config('database.connections.pgsql.database'), ['carpdraw_foundation_test', 'carpdraw_ci'], true)) {
    throw new RuntimeException('Concurrency checks require the dedicated PostgreSQL test database.');
}
config(['drawing.provider.default' => 'fake', 'drawing.credits.free' => 12, 'drawing.credits.actions.drawing' => 4]);
Queue::fake();

if (($argv[1] ?? '') === 'worker') {
    try {
        app(ProjectService::class)->create(User::findOrFail($argv[2]), WorkflowMode::Drawing, UploadedFile::fake()->image('chair.jpg'));
        echo 'queued';
    } catch (DrawingFailed $exception) {
        if ($exception->getMessage() !== 'Not enough credits for this creation.') {
            throw $exception;
        }
        echo 'insufficient';
    }
    exit(0);
}

$user = User::factory()->create();
$workers = [];
try {
    for ($i = 0; $i < 8; $i++) {
        $worker = new Process([PHP_BINARY, __FILE__, 'worker', $user->id], base_path());
        $worker->setTimeout(60)->start();
        $workers[] = $worker;
    }
    $results = [];
    foreach ($workers as $worker) {
        $worker->wait();
        if (! $worker->isSuccessful()) {
            throw new RuntimeException($worker->getErrorOutput().$worker->getOutput());
        }
        $results[] = trim($worker->getOutput());
    }
    $queued = count(array_filter($results, fn (string $result): bool => $result === 'queued'));
    $rejected = count(array_filter($results, fn (string $result): bool => $result === 'insufficient'));
    if ($queued !== 3 || $rejected !== 5 || $user->creditBalance() !== 0 || $user->projects()->count() !== 3) {
        throw new RuntimeException('Parallel submissions violated the credit balance: '.json_encode($results));
    }
    echo "8 concurrent requests: 3 queued, 5 rejected, balance 0. No overspend.\n";
} finally {
    foreach ($workers as $worker) {
        if ($worker->isRunning()) {
            $worker->stop();
        }
    }
    foreach (Project::where('user_id', $user->id)->get() as $project) {
        $project->purgeFiles();
    }
    $user->forceDelete();
}
