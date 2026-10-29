<?php

namespace App\Services\Drawing\Concerns;

use App\Services\Drawing\DrawingFailed;

/**
 * The retryable/permanent split, shared by every HTTP-backed provider.
 *
 * The classification is the same argument whoever is on the other end: capacity
 * and credentials are OUR problem and must cost the visitor nothing, a refusal
 * on content is theirs and must stop immediately so their credits come back
 * rather than being burnt over three attempts.
 *
 * Only the error ENVELOPE differs between providers, so each one parses its own
 * body and hands the pieces here.
 */
trait MapsHttpFailures
{
    /**
     * @param  string  $haystack  Whatever the provider gives that might name a
     *                            safety refusal - an error type, a status enum,
     *                            a finish reason. Matched case-insensitively.
     */
    private function failureFor(int $status, string $message, string $haystack = ''): DrawingFailed
    {
        if ($status === 429) {
            return DrawingFailed::retryable('rate_limited', $message);
        }

        if ($status >= 500) {
            return DrawingFailed::retryable('provider_unavailable', $message);
        }

        if ($status === 401 || $status === 403) {
            // A key problem is ours, not the visitor's - surface it loudly in
            // logs but never burn their credits over it.
            return DrawingFailed::retryable('provider_auth', $message);
        }

        if ($this->readsAsModeration($haystack.' '.$message)) {
            return DrawingFailed::permanent('moderation', $message);
        }

        return DrawingFailed::permanent('provider_rejected', $message);
    }

    /**
     * Whether a refusal is about the CONTENT rather than the request.
     *
     * Matched on the provider's own vocabulary: OpenAI says "moderation" and
     * "safety system", Google says "blocked", "PROHIBITED_CONTENT" and
     * "SAFETY". A miss here is not silent - it falls through to
     * `provider_rejected`, which is permanent and refunds all the same; it only
     * costs the visitor the better error message.
     */
    private function readsAsModeration(string $haystack): bool
    {
        $haystack = strtolower($haystack);

        foreach (['moderation', 'safety', 'blocked', 'prohibited', 'harm_category'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
