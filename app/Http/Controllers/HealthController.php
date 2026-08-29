<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
        ];

        // Redis is only load-bearing when something is configured to use it;
        // the sqlite/file dev setup must not report unhealthy.
        if ($this->usesRedis()) {
            $checks['redis'] = $this->checkRedis();
        }

        $healthy = ! in_array(false, array_column($checks, 'healthy'), true);

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    private function usesRedis(): bool
    {
        return config('cache.default') === 'redis'
            || config('queue.default') === 'redis'
            || config('session.driver') === 'redis';
    }

    /**
     * @return array{healthy: bool, latency_ms?: float, error?: string}
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            $latency = $this->measureLatency(fn () => DB::select('SELECT 1'));

            return ['healthy' => true, 'latency_ms' => $latency];
        } catch (\Throwable) {
            return ['healthy' => false, 'error' => 'Database connection failed'];
        }
    }

    /**
     * @return array{healthy: bool, latency_ms?: float, error?: string}
     */
    private function checkRedis(): array
    {
        try {
            $latency = $this->measureLatency(fn () => Redis::ping());

            return ['healthy' => true, 'latency_ms' => $latency];
        } catch (\Throwable) {
            return ['healthy' => false, 'error' => 'Redis connection failed'];
        }
    }

    /**
     * @return array{healthy: bool, error?: string}
     */
    private function checkCache(): array
    {
        try {
            $key = 'health_check_'.uniqid();
            Cache::put($key, 'ok', 10);
            $value = Cache::get($key);
            Cache::forget($key);

            return ['healthy' => $value === 'ok'];
        } catch (\Throwable) {
            return ['healthy' => false, 'error' => 'Cache check failed'];
        }
    }

    /**
     * @return array{healthy: bool, used_percent?: float, free_gb?: float, error?: string}
     */
    private function checkStorage(): array
    {
        try {
            $path = storage_path();
            $freeBytes = disk_free_space($path);
            $totalBytes = disk_total_space($path);

            if ($freeBytes === false || $totalBytes === false || $totalBytes < 1) {
                return ['healthy' => false, 'error' => 'Unable to read disk space'];
            }

            $usedPercent = round((1 - $freeBytes / $totalBytes) * 100, 1);

            return [
                'healthy' => $usedPercent < 90,
                'used_percent' => $usedPercent,
                'free_gb' => round($freeBytes / 1024 / 1024 / 1024, 2),
            ];
        } catch (\Throwable) {
            return ['healthy' => false, 'error' => 'Storage check failed'];
        }
    }

    private function measureLatency(callable $callback): float
    {
        $start = microtime(true);
        $callback();

        return round((microtime(true) - $start) * 1000, 2);
    }
}
