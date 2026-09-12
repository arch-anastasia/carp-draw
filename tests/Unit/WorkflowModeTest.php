<?php

namespace Tests\Unit;

use App\Enums\WorkflowMode;
use App\Services\Drawing\DrawingFailed;
use PHPUnit\Framework\TestCase;

class WorkflowModeTest extends TestCase
{
    public function test_client_dialect_round_trips(): void
    {
        foreach (WorkflowMode::cases() as $mode) {
            $this->assertSame($mode, WorkflowMode::fromClient($mode->clientValue()));
        }

        $this->assertSame(WorkflowMode::ModelDrawing, WorkflowMode::fromClient('modelDrawing'));
        $this->assertNull(WorkflowMode::fromClient('garbage'));
    }

    public function test_each_mode_has_a_distinct_route_and_label(): void
    {
        $routes = array_map(fn (WorkflowMode $mode) => $mode->routeName(), WorkflowMode::cases());
        $labels = array_map(fn (WorkflowMode $mode) => $mode->typeLabel(), WorkflowMode::cases());

        $this->assertCount(3, array_unique($routes));
        $this->assertCount(3, array_unique($labels));
    }

    public function test_user_rejections_are_distinguished_from_our_failures(): void
    {
        $this->assertTrue(DrawingFailed::permanent('insufficient_credits', 'x')->isExpectedUserRejection());
        $this->assertTrue(DrawingFailed::permanent('moderation', 'x')->isExpectedUserRejection());
        $this->assertFalse(DrawingFailed::retryable('provider_unavailable', 'x')->isExpectedUserRejection());
        $this->assertFalse(DrawingFailed::permanent('worker_died', 'x')->isExpectedUserRejection());
    }

    public function test_visitor_messages_never_leak_provider_detail(): void
    {
        $failure = DrawingFailed::permanent('provider_rejected', 'HTTP 400 from api.example.com: token abc123 rejected');

        $this->assertStringNotContainsString('api.example.com', $failure->visitorMessage());
        $this->assertStringNotContainsString('abc123', $failure->visitorMessage());
        $this->assertStringContainsString('credits are untouched', $failure->visitorMessage());
    }
}
