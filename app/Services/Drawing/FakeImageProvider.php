<?php

namespace App\Services\Drawing;

use App\Enums\WorkflowMode;

/**
 * Stands in for the real model in tests and on machines with no API key.
 *
 * It returns the bundled sample sheet for the requested workflow, so the
 * whole pipeline can be exercised end to end without spending money — and it
 * is bound ONLY when no key is configured, so production can never quietly
 * serve a sample as if it were the visitor's own drawing.
 */
class FakeImageProvider implements ImageProvider
{
    /**
     * Sample output per workflow, from the shipped example assets.
     *
     * @var array<string, string>
     */
    private const SAMPLES = [
        'drawing' => 'examples/gallery/oak-sideboard-line-drawing.webp',
        'model_drawing' => 'examples/example-bunk-drawing.webp',
        'specification' => 'examples/shelving-technical-sheet.webp',
    ];

    public function name(): string
    {
        return 'fake';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function edit(
        string $imageContents,
        string $filename,
        string $prompt,
        ?WorkflowMode $mode = null,
    ): ProviderResult {
        $key = $mode instanceof WorkflowMode ? $mode->value : 'drawing';
        $sample = public_path(self::SAMPLES[$key]);

        $contents = is_readable($sample) ? (string) file_get_contents($sample) : $imageContents;

        return new ProviderResult(
            contents: $contents,
            extension: is_readable($sample) ? 'webp' : 'png',
            model: 'fake-drawing-1',
            meta: ['fake' => true],
        );
    }
}
