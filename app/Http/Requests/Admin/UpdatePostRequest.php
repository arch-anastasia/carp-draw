<?php

namespace App\Http\Requests\Admin;

use App\Models\Post;
use App\Rules\HasFaqSection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200'],
            'target_keyword' => ['nullable', 'string', 'max:200'],
            'secondary_keywords' => ['nullable', 'array'],
            'secondary_keywords.*' => ['string', 'max:200'],
            /*
             * The byline, in the order the editor picked the names.
             * Empty is allowed and means house copy: Post::syncAuthors()
             * credits the house account rather than leaving an article
             * unsigned.
             */
            'authors' => ['nullable', 'array'],
            'authors.*' => ['uuid', 'exists:authors,id'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            /*
             * A draft may be half-written, so the FAQ is only required once a
             * publish date is set: every live article renders its FAQ as
             * FAQPage JSON-LD. See App\Rules\HasFaqSection.
             */
            'body' => ['required', 'string', Rule::when(
                filled($this->input('published_at')),
                [new HasFaqSection],
            )],
            'cover_image_path' => ['nullable', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'audience' => ['nullable', 'string', 'max:100'],
            /*
             * The cut the offer beside the article demonstrates. A catalog
             * look, not a demo tile - the face still follows the audience,
             * and this only names which of that face's pre-generated tiles
             * the "after" half shows. Null is the common case.
             */
            'cta_text' => ['nullable', 'string', 'max:255'],
            'cta_url' => ['nullable', 'string', 'max:500'],
            'published_at' => ['nullable', 'date'],
            /*
             * The dateline. No `before` or `after` rule on purpose: dating an
             * article ahead is the point of the field, and dating one back is
             * how a rewritten guide stops claiming to be new.
             */
            'dated_at' => ['nullable', 'date'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['uuid', 'exists:categories,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->route('post')?->isKilled() && filled($this->input('published_at'))) {
                $validator->errors()->add('published_at', 'This post is killed. Un-kill it before publishing.');
            }

            $slug = trim((string) $this->input('slug'));

            if ($slug !== '' && Post::blogSlugTaken($slug, $this->route('post')?->id)) {
                $validator->errors()->add('slug', 'That slug is already used by another post, a category or a reserved page.');
            }
        });
    }
}
