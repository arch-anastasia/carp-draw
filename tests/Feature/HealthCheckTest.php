<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_healthy(): void
    {
        $this->get('/health')
            ->assertOk()
            ->assertJsonPath('status', 'healthy')
            ->assertJsonStructure([
                'status',
                'timestamp',
                'version',
                'checks' => ['database', 'cache', 'storage'],
            ]);
    }

    public function test_framework_liveness_route_answers(): void
    {
        $this->get('/up')->assertOk();
    }
}
