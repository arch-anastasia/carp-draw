<?php

namespace App\Services\Drawing;

use App\Enums\WorkflowMode;
use App\Services\Drawing\Concerns\MapsHttpFailures;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OpenRouterImageProvider implements ImageProvider
{
    use MapsHttpFailures;

    public function name(): string
    {
        return 'openrouter';
    }

    public function isConfigured(): bool
    {
        return (string) config('drawing.provider.drivers.openrouter.key') !== '';
    }

    public function edit(string $imageContents, string $filename, string $prompt, ?WorkflowMode $mode = null): ProviderResult
    {
        $info = @getimagesizefromstring($imageContents);
        if ($info === false || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw DrawingFailed::permanent('provider_rejected', 'A readable JPEG, PNG or WebP image is required.');
        }

        $model = (string) config('drawing.provider.drivers.openrouter.model');
        try {
            $response = Http::withToken((string) config('drawing.provider.drivers.openrouter.key'))
                ->timeout((int) config('drawing.provider.drivers.openrouter.timeout', 60))
                ->connectTimeout(15)
                ->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model' => $model,
                    'modalities' => ['image', 'text'],
                    'messages' => [['role' => 'user', 'content' => [
                        ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$info['mime'].';base64,'.base64_encode($imageContents)]],
                        ['type' => 'text', 'text' => $prompt],
                    ]]],
                ]);
        } catch (ConnectionException) {
            throw DrawingFailed::retryable('provider_unavailable', 'The image provider could not be reached.');
        }

        if ($response->failed()) {
            throw $this->failureFor($response->status(), 'The image provider rejected the request.');
        }

        $url = $response->json('choices.0.message.images.0.image_url.url');
        if (! is_string($url) || ! preg_match('#^data:image/(png|jpeg|webp);base64,(.+)$#s', $url, $matches)) {
            throw DrawingFailed::permanent('empty_response', 'The image provider returned no inline image.');
        }
        $bytes = base64_decode($matches[2], true);
        $output = $bytes !== false ? @getimagesizefromstring($bytes) : false;
        if ($output === false || ! in_array($output['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)) {
            throw DrawingFailed::permanent('bad_payload', 'The image provider returned invalid image bytes.');
        }

        return new ProviderResult($bytes, match ($output['mime']) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        }, $model);
    }
}
