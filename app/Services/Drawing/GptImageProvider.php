<?php

namespace App\Services\Drawing;

use App\Enums\WorkflowMode;
use App\Services\Drawing\Concerns\MapsHttpFailures;
use App\Services\Drawing\Concerns\ScalesInputImages;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI's image model, driven through the images/edits endpoint.
 *
 * Edits — not generations: the product is "this piece, redrawn as a
 * technical sheet", so the visitor's photo is the input and the prompt only
 * describes the transformation. A generation endpoint would invent a piece
 * of furniture, which is the one thing this product must never do.
 */
class GptImageProvider implements ImageProvider
{
    use MapsHttpFailures;
    use ScalesInputImages;

    public function name(): string
    {
        return 'openai';
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

        $imageContents = $this->downscaled($imageContents);

        $fields = [
            ['name' => 'model', 'contents' => $model],
            ['name' => 'prompt', 'contents' => $prompt],
            ['name' => 'size', 'contents' => (string) $this->setting('size')],
            ['name' => 'quality', 'contents' => (string) $this->setting('quality')],
            ['name' => 'n', 'contents' => '1'],
        ];

        try {
            $response = Http::withToken((string) $this->setting('key'))
                ->timeout((int) $this->setting('timeout', 120))
                ->connectTimeout(15)
                ->asMultipart()
                ->attach('image', $imageContents, $filename)
                ->post(rtrim((string) $this->setting('base_url'), '/').'/images/edits', $fields);
        } catch (ConnectionException $e) {
            throw DrawingFailed::retryable('provider_unavailable', 'Could not reach the image provider: '.$e->getMessage());
        }

        if ($response->failed()) {
            throw $this->classify($response);
        }

        $payload = $response->json('data.0.b64_json');

        if (! is_string($payload) || $payload === '') {
            throw DrawingFailed::retryable('empty_response', 'The provider returned no image data.');
        }

        $decoded = base64_decode($payload, true);

        if ($decoded === false || $decoded === '') {
            throw DrawingFailed::retryable('bad_payload', 'The provider returned an image that could not be decoded.');
        }

        return new ProviderResult(
            contents: $decoded,
            extension: 'png',
            model: $model,
            meta: [
                // Identifiers only: never the prompt, never the image.
                'request_id' => $response->header('x-request-id') ?: null,
                'usage' => $response->json('usage'),
            ],
        );
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        return config("drawing.provider.drivers.openai.{$key}", $default);
    }

    /**
     * Map a provider error onto something the pipeline can act on. OpenAI's
     * envelope is `error.type` plus `error.message`.
     */
    private function classify(Response $response): DrawingFailed
    {
        return $this->failureFor(
            $response->status(),
            (string) $response->json('error.message', 'The provider rejected the request.'),
            (string) $response->json('error.type', ''),
        );
    }
}
