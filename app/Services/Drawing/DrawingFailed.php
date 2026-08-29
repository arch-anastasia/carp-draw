<?php

namespace App\Services\Drawing;

use RuntimeException;

/**
 * A generation that will not succeed as asked.
 *
 * `retryable` is the whole point of this class: a rate limit or a network
 * blip should come back around, while a moderation refusal or a malformed
 * source should stop immediately and release the visitor's credits.
 */
class DrawingFailed extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly bool $retryable = false,
    ) {
        parent::__construct($message);
    }

    public static function retryable(string $errorCode, string $message): self
    {
        return new self($errorCode, $message, true);
    }

    public static function permanent(string $errorCode, string $message): self
    {
        return new self($errorCode, $message, false);
    }

    /**
     * Whether the visitor can resolve this rejection by changing their input.
     */
    public function isExpectedUserRejection(): bool
    {
        return in_array($this->errorCode, [
            'bad_image',
            'moderation',
            'unsupported_source',
            'insufficient_credits',
            'plan_required',
        ], true);
    }

    /**
     * Copy for the person waiting on the result. Never leaks provider detail.
     */
    public function visitorMessage(): string
    {
        return match ($this->errorCode) {
            'moderation' => 'That file was rejected by the safety filter. Try a clear photo of the furniture piece.',
            'bad_image' => "We couldn't read that image. Try a clear, well-lit photo of the piece.",
            'unsupported_source' => 'This source format is not supported for processing yet. Try a photo instead.',
            'insufficient_credits' => 'You are out of creation credits. Upgrade your plan to continue.',
            'plan_required' => '3D model processing is available on the Studio plan.',
            'not_live' => 'Drawing generation is not available right now. Your credits are untouched.',
            'rate_limited', 'provider_unavailable' => 'The generator is busy. Your credits are untouched — try again in a minute.',
            'timeout' => 'That generation took too long and was stopped. Your credits are untouched.',
            default => 'That generation did not complete. Your credits are untouched.',
        };
    }
}
