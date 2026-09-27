<?php

namespace App\Http\Requests\Api;

use App\Models\Post;
use App\Rules\HasFaqSection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class BlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    abstract protected function existingPost(): ?Post;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:200'],
            'target_keyword' => ['nullable', 'string', 'max:200'],
            'secondary_keywords' => ['nullable', 'array'],
            'secondary_keywords.*' => ['string', 'max:200'],

            /*
             * Empty is allowed and means house copy - Post::syncAuthors()
             * credits the house account rather than publishing unsigned.
             */
            'authors' => ['nullable', 'array'],
            'authors.*' => [
                'string',
                Rule::exists('authors', 'username')->whereNull('deleted_at'),
            ],

            'excerpt' => ['nullable', 'string', 'max:500'],

            /*
             * A draft may be half-written, so the FAQ is only required once a
             * publish date is set: every live article renders its FAQ as
             * FAQPage JSON-LD. See App\Rules\HasFaqSection.
             *
             * On an update this reads the merged state, not the payload -
             * UpdateBlogPostRequest fills the untouched fields in from the row
             * first, so "publish this existing draft" is checked against the
             * body it will actually publish.
             */
            'body' => ['required', 'string', Rule::when(
                filled($this->input('published_at')),
                [new HasFaqSection],
            )],

            /*
             * An absolute URL or a site-relative path; Media::forUrl takes
             * both. Bytes do not arrive here - POST /api/v1/blog/images takes
             * those and hands back the URL this field wants.
             */
            'cover_image' => ['nullable', 'string', 'max:255'],

            /*
             * What the cover shows, for a reader who cannot see it. Stored on
             * the media row rather than the post, because the description
             * belongs to the picture and the same picture may open more than
             * one article.
             */
            'cover_image_alt' => ['nullable', 'string', 'max:255'],

            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'audience' => ['nullable', 'string', 'max:100'],

            /*
             * The cut the offer beside the article demonstrates, by catalog
             * key rather than by id - the same rule the byline and the filing
             * follow, because a key is an identifier a drafting agent read in
             * the context document and can reproduce.
             *
             * Not every catalog look has been photographed on both sides'
             * faces, so this is checked against the shot ones only: a look
             * with no tile behind it would be accepted, stored, and then
             * silently fall back at render time, which is a worse answer than
             * a validation error naming what is available.
             */

            'cta_text' => ['nullable', 'string', 'max:255'],
            'cta_url' => ['nullable', 'string', 'max:500'],

            /* Null keeps it a draft, a past time publishes, a future one schedules. */
            'published_at' => ['nullable', 'date'],
            'dated_at' => ['nullable', 'date'],
            'killed_at' => ['nullable', 'date'],
            'killed_reason' => ['nullable', 'string', 'max:255'],

            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', Rule::exists('categories', 'slug')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (filled($this->input('published_at')) && ($this->existingPost()?->isKilled() || filled($this->input('killed_at')))) {
                $validator->errors()->add('published_at', 'This post is killed. Un-kill it in a separate request before publishing.');
            }

            $slug = trim((string) $this->input('slug'));

            if ($slug !== '' && Post::blogSlugTaken($slug, $this->existingPost()?->id)) {
                $validator->errors()->add('slug', 'That slug is already used by another post, a category or a reserved page.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'authors.*.exists' => 'No writer on this blog has the username :input. GET /api/v1/blog/context lists the ones that do.',
            'categories.*.exists' => 'There is no category slugged :input. GET /api/v1/blog/context lists them.',
            'audience.exists' => 'Unknown audience :input. GET /api/v1/blog/context lists the ones this catalog has.',
            'look.in' => 'No demo photograph exists of :input, so the offer beside the article cannot show it. GET /api/v1/blog/context lists the cuts that can.',
        ];
    }
}
