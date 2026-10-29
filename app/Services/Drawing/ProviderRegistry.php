<?php

namespace App\Services\Drawing;

use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

/**
 * Which backend answers, and whether it is allowed to.
 *
 * This is the only place that knows a driver name. Adding a backend is a
 * block in `drawing.provider.drivers` naming a class; switching to it is
 * `DRAWING_PROVIDER`.
 */
class ProviderRegistry
{
    /**
     * The image provider class per driver. In config the driver block only
     * carries settings; the class map lives here so config stays data.
     *
     * @var array<string, class-string<ImageProvider>>
     */
    private const IMAGE_PROVIDERS = [
        'fake' => FakeImageProvider::class,
        'openrouter' => OpenRouterImageProvider::class,
        'gemini' => GeminiImageProvider::class,
        'openai' => GptImageProvider::class,
    ];

    public function __construct(private Application $app) {}

    /** The configured backend, whether or not it is usable. */
    public function driver(): string
    {
        return (string) config('drawing.provider.default', 'fake');
    }

    /** The driver that will actually answer (fake when a key is missing). */
    public function effectiveDriver(): string
    {
        return $this->needsMissingKey() ? 'fake' : $this->driver();
    }

    /**
     * The image model for the active driver, or the stand-in when the driver
     * needs a key and has not been given one.
     */
    public function imageProvider(): ImageProvider
    {
        $driver = $this->effectiveDriver();

        $class = self::IMAGE_PROVIDERS[$driver] ?? null;

        if ($class === null) {
            throw new RuntimeException(
                "The [{$driver}] drawing driver has no provider class. ".
                'Add one to ProviderRegistry::IMAGE_PROVIDERS.',
            );
        }

        return $this->app->make($class);
    }

    /**
     * Whether the active backend may generate for a PAYING CUSTOMER.
     *
     * Reads the driver CATALOGUE flags rather than asking the resolved
     * provider: the stand-in always reports itself configured, so trusting
     * the instance would let a production box with no API key serve sample
     * sheets as if they were the visitor's own drawing.
     */
    public function isLive(): bool
    {
        if ($this->needsMissingKey()) {
            return false;
        }

        $production = (bool) config("drawing.provider.drivers.{$this->driver()}.production", false);

        if (! $production) {
            return ! $this->app->environment('production');
        }

        return true;
    }

    /**
     * Whether the configured driver has been downgraded to the stand-in for
     * want of a key.
     */
    public function needsMissingKey(): bool
    {
        $driver = $this->driver();

        return (bool) config("drawing.provider.drivers.{$driver}.requires_key", false)
            && (string) config("drawing.provider.drivers.{$driver}.key", '') === '';
    }

    /**
     * Whether the EFFECTIVE backend can process this source at all.
     *
     * The stand-in fakes anything. Real image models need an image: a .skp
     * or a PDF has no pixels to edit, so those sources are refused up front
     * — before credits move — rather than failing in the job. Per driver,
     * because the input formats differ: Gemini reads HEIC/HEIF natively,
     * OpenAI's edits endpoint does not (and GD cannot transcode HEIC), so
     * an iPhone-default photo on the openai driver would burn a full retry
     * cycle and fail.
     */
    public function supportsSource(string $extension): bool
    {
        $extension = strtolower($extension);

        return match ($this->effectiveDriver()) {
            'fake' => true,
            'gemini' => in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'], true),
            default => in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true),
        };
    }
}
