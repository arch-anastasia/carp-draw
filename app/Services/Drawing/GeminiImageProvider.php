<?php

namespace App\Services\Drawing;

use App\Enums\WorkflowMode;
use App\Services\Drawing\Concerns\MapsHttpFailures;
use App\Services\Drawing\Concerns\ReadsInteractionTurns;
use App\Services\Drawing\Concerns\ScalesInputImages;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Google's Gemini image models, driven through the Interactions API.
 *
 * The visitor's reference photo goes in as an image block and the prompt only
 * describes the TRANSFORMATION — the same object, redrawn to the CarpDraw
 * technical standard. A text-to-image model would invent a piece of furniture
 * instead, which is the one thing this product must never do.
 *
 * Failures are classified rather than swallowed. The job retries the ones
 * worth retrying and refunds the rest, so a 429 costs the visitor nothing and
 * a safety refusal does not spin for three attempts.
 */
class GeminiImageProvider implements ImageProvider
{
    use MapsHttpFailures;
    use ReadsInteractionTurns;
    use ScalesInputImages;

    public function name(): string
    {
        return 'gemini';
    }

    public function isConfigured(): bool
    {
        return (string) $this->setting('key') !== '';
    }

    public function edit(
        string $imageContents,
        string $filename,
        string $prompt,
        ?WorkflowMode $mode = null,
    ): ProviderResult {
        $model = (string) $this->setting('model');

        /*
         * Input image tokens are billed on the resolution SENT, so a 4,000px
         * photo is paid for in full and then discarded. The full-size
         * original stays on disk.
         */
        $imageContents = $this->downscaled($imageContents);

        $payload = [
            'model' => $model,
            /*
             * Image first, then the instruction — the order is the order the
             * model reads them in.
             */
            'input' => [
                [
                    'type' => 'image',
                    // From the BYTES, not the filename: ScalesInputImages
                    // re-encodes oversized uploads to JPEG, so the original
                    // extension may no longer describe the payload.
                    'mime_type' => $this->mimeOf($imageContents, $filename),
                    'data' => base64_encode($imageContents),
                ],
                ['type' => 'text', 'text' => $prompt],
            ],
            'response_format' => [
                'type' => 'image',
            ],
            // Nothing about a customer's product photo is worth keeping on
            // Google's side.
            'store' => false,
        ];

        try {
            $response = Http::withHeaders(['x-goog-api-key' => (string) $this->setting('key')])
                ->timeout((int) $this->setting('timeout', 60))
                ->connectTimeout(15)
                ->post(rtrim((string) $this->setting('base_url'), '/').'/interactions', $payload);
        } catch (ConnectionException $e) {
            throw DrawingFailed::retryable('provider_unavailable', 'Could not reach the image provider: '.$e->getMessage());
        }

        if ($response->failed()) {
            throw $this->classify($response);
        }

        $image = $this->firstImageBlock($response->json('steps', []));

        if ($image === null) {
            throw $this->classifyMissingImage($response);
        }

        $decoded = base64_decode((string) ($image['data'] ?? ''), true);

        if ($decoded === false || $decoded === '') {
            throw DrawingFailed::retryable('bad_payload', 'The provider returned an image that could not be decoded.');
        }

        return new ProviderResult(
            contents: $decoded,
            extension: $this->extensionFor((string) ($image['mime_type'] ?? '')),
            model: $model,
            meta: [
                // Identifiers only: never the prompt, never the image.
                'request_id' => $response->json('id'),
                'usage' => $response->json('usage'),
            ],
        );
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        return config("drawing.provider.drivers.gemini.{$key}", $default);
    }

    /**
     * The first image the MODEL produced. A scan, but never of the echoed
     * `user_input` step — that carries the visitor's own upload, and
     * returning it would hand back an unedited photograph as the drawing
     * they just paid for. See ReadsInteractionTurns.
     *
     * @param  array<int, mixed>  $steps
     * @return array<string, mixed>|null
     */
    private function firstImageBlock(array $steps): ?array
    {
        foreach ($this->modelBlocks($steps) as $block) {
            if (($block['type'] ?? null) === 'image' && ($block['data'] ?? '') !== '') {
                return $block;
            }
        }

        return null;
    }

    /**
     * A 200 that carried no picture — usually a safety stop, which the
     * Interactions API reports in the body rather than as an HTTP error.
     * Reads the STATUS and what the model actually said, never the raw body
     * (which echoes our own request back, upload included).
     */
    private function classifyMissingImage(Response $response): DrawingFailed
    {
        $status = (string) $response->json('status', '');

        $said = array_map(
            fn (array $block) => (string) ($block['text'] ?? ''),
            array_filter(
                $this->modelBlocks((array) $response->json('steps', [])),
                fn (array $block) => ($block['type'] ?? null) === 'text',
            ),
        );

        if ($this->readsAsModeration($status.' '.implode(' ', $said))) {
            return DrawingFailed::permanent(
                'moderation',
                'The provider refused to process that file: '.($status ?: 'blocked'),
            );
        }

        return DrawingFailed::retryable('empty_response', 'The provider returned no image data.');
    }

    /**
     * Map a provider error onto something the pipeline can act on. Google's
     * envelope is `error.status` (an enum such as RESOURCE_EXHAUSTED) plus
     * `error.message`.
     */
    private function classify(Response $response): DrawingFailed
    {
        return $this->failureFor(
            $response->status(),
            (string) $response->json('error.message', 'The provider rejected the request.'),
            (string) $response->json('error.status', ''),
        );
    }

    /**
     * The payload's actual mime, with the filename as fallback for formats
     * getimagesize cannot read (HEIC passes through downscaling untouched).
     */
    private function mimeOf(string $contents, string $filename): string
    {
        $info = @getimagesizefromstring($contents);

        if ($info !== false && $info['mime'] !== '') {
            return $info['mime'];
        }

        return match (strtolower((string) pathinfo($filename, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'heic', 'heif' => 'image/heic',
            default => 'image/jpeg',
        };
    }

    /**
     * The extension the bytes actually deserve — read off the response
     * rather than assumed, because the stored path is built from it.
     */
    private function extensionFor(string $mime): string
    {
        return match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };
    }
}
