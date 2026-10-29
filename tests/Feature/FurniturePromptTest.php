<?php

namespace Tests\Feature;

use App\Enums\WorkflowMode;
use App\Models\Project;
use App\Services\Drawing\DrawingFailed;
use App\Services\Drawing\OpenRouterImageProvider;
use App\Services\Drawing\PromptBuilder;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FurniturePromptTest extends TestCase
{
    public function test_four_view_brief_preserves_dimensions_without_inventing_measurements(): void
    {
        $project = new Project(['workflow' => WorkflowMode::Drawing, 'settings' => [
            'style' => 'furniture four views', 'width' => 1800, 'height' => 600, 'depth' => 400, 'unit' => 'mm',
        ]]);
        $prompt = app(PromptBuilder::class)->buildFor($project);
        $this->assertStringContainsString('1800 × 600 × 400 mm', $prompt);
        $this->assertStringContainsString('exactly four views', $prompt);
        $this->assertStringContainsString('Never invent internal dimensions', $prompt);
        $this->assertStringContainsString('NOT FOR MANUFACTURE', $prompt);
    }

    public function test_openrouter_reads_inline_image_and_sends_original_source(): void
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j5H8AAAAASUVORK5CYII=');
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['images' => [
            ['image_url' => ['url' => 'data:image/png;base64,'.base64_encode($bytes)]],
        ]]]]])]);
        $result = app(OpenRouterImageProvider::class)->edit($bytes, 'photo.png', 'four views');
        $this->assertSame($bytes, $result->contents);
        Http::assertSent(fn ($request) => $request['messages'][0]['content'][0]['image_url']['url'] === 'data:image/png;base64,'.base64_encode($bytes));
    }

    public function test_openrouter_never_fetches_provider_returned_remote_urls(): void
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j5H8AAAAASUVORK5CYII=');
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['images' => [
            ['image_url' => ['url' => 'http://127.0.0.1/private']],
        ]]]]])]);
        $this->expectException(DrawingFailed::class);
        app(OpenRouterImageProvider::class)->edit($bytes, 'photo.png', 'four views');
    }
}
