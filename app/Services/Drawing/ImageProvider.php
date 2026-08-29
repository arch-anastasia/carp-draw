<?php

namespace App\Services\Drawing;

use App\Enums\WorkflowMode;

/**
 * The seam every image model plugs into.
 */
interface ImageProvider
{
    /**
     * Render `$prompt` onto `$imageContents`, returning the generated sheet.
     *
     * `$mode` tells a stand-in which kind of output to fake; real providers
     * are free to ignore it — the prompt already carries the instruction.
     *
     * @throws DrawingFailed
     */
    public function edit(
        string $imageContents,
        string $filename,
        string $prompt,
        ?WorkflowMode $mode = null,
    ): ProviderResult;

    /** Whether this provider is configured enough to be called. */
    public function isConfigured(): bool;

    public function name(): string;
}
