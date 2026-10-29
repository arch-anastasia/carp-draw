<?php

namespace App\Http\Requests\Admin;

use App\Models\Post;
use App\Rules\HasFaqSection;
use App\Services\BlogContent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostInlineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            /*
             * `nullable` and blank both mean "derive it from the title" -
             * the same reading the admin form gives an empty slug field.
             */
            'slug' => ['sometimes', 'nullable', 'string', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:200'],
            'target_keyword' => ['sometimes', 'nullable', 'string', 'max:200'],
            'secondary_keywords' => ['sometimes', 'nullable', 'array'],
            'secondary_keywords.*' => ['string', 'max:200'],
            'authors' => ['sometimes', 'nullable', 'array'],
            'authors.*' => ['uuid', 'exists:authors,id'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:500'],
            'body' => ['sometimes', 'string', Rule::when(
                $this->isPublishing(),
                [new HasFaqSection],
            )],
            'cover_image_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cover_image_alt' => ['sometimes', 'nullable', 'string', 'max:500'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:300'],
            'audience' => ['sometimes', 'nullable', 'string', 'max:100'],
            /*
             * The cut the offer beside the article demonstrates. A catalog
             * look, not a demo tile - the face still follows the audience,
             * and this only names which of that face's pre-generated tiles
             * the "after" half shows. Null is the common case.
             */
            'cta_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cta_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            /*
             * The dateline. No `before` or `after` rule, for the reason the
             * admin form gives: dating an article ahead is the point of the
             * field, and dating one back is how a rewritten guide stops
             * claiming to be new.
             */
            'dated_at' => ['sometimes', 'nullable', 'date'],
            'categories' => ['sometimes', 'nullable', 'array'],
            'categories.*' => ['uuid', 'exists:categories,id'],

            /*
             * The questions under the article, edited in the accordion that
             * prints them. They are stored as a "## FAQ" section of the body
             * rather than as rows, so they are folded back into it below and
             * never reach the model as a field of their own.
             */
            'faq' => ['sometimes', 'array'],
            'faq.*.question' => ['required', 'string', 'max:300'],
            'faq.*.answer' => ['required', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('body') && ! $this->has('faq') && ! $this->isPublishing()) {
            return;
        }

        $content = app(BlogContent::class);
        $stored = (string) $this->article()?->body;

        $prose = $this->has('body')
            ? trim((string) $this->input('body'))
            : $content->withoutFaqSection($stored);

        /*
         * Belt and braces: the editor strips the FAQ out of what it sends, but
         * a body that arrived with one anyway must not end up with two.
         */
        $prose = $content->withoutFaqSection($prose);

        $faq = $this->has('faq')
            ? $content->faqSection($this->faqItems())
            : $content->faqSectionSource($stored);

        $this->merge(['body' => trim($prose."\n\n".$faq)]);
    }

    /**
     * @return array<int, array{question: string, answer: string}>
     */
    private function faqItems(): array
    {
        return collect((array) $this->input('faq', []))
            ->map(fn (mixed $item): array => [
                'question' => trim((string) (is_array($item) ? ($item['question'] ?? '') : '')),
                'answer' => trim((string) (is_array($item) ? ($item['answer'] ?? '') : '')),
            ])
            ->all();
    }

    private function isPublishing(): bool
    {
        if (! $this->willBePublished()) {
            return false;
        }

        return $this->has('published_at') || $this->has('body') || $this->has('faq');
    }

    private function willBePublished(): bool
    {
        if ($this->has('published_at')) {
            return filled($this->input('published_at'));
        }

        return $this->article()?->published_at !== null;
    }

    private function article(): ?Post
    {
        $post = $this->route('post');

        return $post instanceof Post ? $post : null;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->article()?->isKilled() && filled($this->input('published_at'))) {
                $validator->errors()->add('published_at', 'This post is killed. Un-kill it before publishing.');
            }

            if (! $this->has('slug')) {
                return;
            }

            $slug = trim((string) $this->input('slug'));

            if ($slug !== '' && Post::blogSlugTaken($slug, $this->article()?->id)) {
                $validator->errors()->add('slug', 'That slug is already used by another post, a category or a reserved page.');
            }
        });
    }
}
