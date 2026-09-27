<?php

namespace App\Rules;

use App\Services\BlogContent;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class HasFaqSection implements ValidationRule
{
    public const MINIMUM_QUESTIONS = 2;

    public const RECOMMENDED_QUESTIONS = 5;

    public function __construct(private readonly int $minimumQuestions = self::MINIMUM_QUESTIONS) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string containing a FAQ section.');

            return;
        }

        $items = app(BlogContent::class)->faqItems($value);

        if (count($items) >= $this->minimumQuestions) {
            return;
        }

        $fail(sprintf(
            'The :attribute needs a "## FAQ" section with at least %d questions, each as a "### Question" heading followed by its answer. Found %d.',
            $this->minimumQuestions,
            count($items),
        ));
    }
}
