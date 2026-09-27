<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePostRevisionRequest;
use App\Models\Author;
use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostRevision;
use App\Rules\HasFaqSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class PostRevisionController extends Controller
{
    public function store(Post $post): RedirectResponse
    {
        $post->load(['authors', 'categories']);

        $revision = $post->pendingRevision() ?? PostRevision::openFor($post);

        return redirect()->route('admin.posts.revisions.edit', [$post->id, $revision->id]);
    }

    public function edit(Post $post, PostRevision $revision): Response
    {
        $this->guard($post, $revision);

        return Inertia::render('Admin/Blog/Revisions/Edit', [
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'url' => $post->url(),
            ],
            'revision' => $this->payload($revision),
            'diff' => $revision->diff(),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdatePostRevisionRequest $request, Post $post, PostRevision $revision): RedirectResponse
    {
        $this->guard($post, $revision);

        abort_if(! $revision->isPending(), 403, 'An applied version cannot be edited.');

        $attributes = $request->safe()->except(['cover_image_path', 'authors', 'categories']);

        /*
         * The form asks for a cover URL, which is the right question to put to
         * an editor; storage is a media row. Same resolution the post editor
         * does - see Admin\PostController::attributes().
         */
        $attributes['cover_media_id'] = Media::forUrl($request->validated('cover_image_path'))?->id;

        /*
         * Byline and filing are id lists on a revision rather than pivots, so
         * the form's names are mapped here - the Vue component speaks the same
         * `authors`/`categories` as the post editor either way.
         */
        $attributes['author_ids'] = $request->validated('authors', []);
        $attributes['category_ids'] = $request->validated('categories', []);

        $revision->update($attributes);

        return back()->with('success', 'Version saved.');
    }

    public function diff(Post $post, PostRevision $revision): Response
    {
        $this->guard($post, $revision);

        $revision->load('appliedBy:id,name');

        return Inertia::render('Admin/Blog/Revisions/Diff', [
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'url' => $post->url(),
            ],
            'revision' => $this->payload($revision),
            'diff' => $revision->diff(),
        ]);
    }

    public function apply(Post $post, PostRevision $revision): RedirectResponse
    {
        $this->guard($post, $revision);

        abort_if(! $revision->isPending(), 403, 'That version has already been applied.');

        if ($post->isPublished() || $post->isScheduled()) {
            Validator::make(
                ['body' => $revision->body],
                ['body' => [new HasFaqSection]],
            )->validate();
        }

        $revision->apply(request()->user());

        return redirect()->to($post->fresh()->url())->with('success', 'New version is live.');
    }

    public function destroy(Post $post, PostRevision $revision): RedirectResponse
    {
        $this->guard($post, $revision);

        abort_if(! $revision->isPending(), 403, 'An applied version is part of the history.');

        $revision->delete();

        return redirect()->route('admin.posts.edit', $post->id)->with('success', 'Draft version discarded.');
    }

    private function guard(Post $post, PostRevision $revision): void
    {
        abort_if($revision->post_id !== $post->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PostRevision $revision): array
    {
        return [
            'id' => $revision->id,
            'title' => $revision->title,
            'excerpt' => $revision->excerpt,
            'body' => $revision->body,
            'cover_image_path' => $revision->cover?->url,
            'meta_title' => $revision->meta_title,
            'meta_description' => $revision->meta_description,
            'audience' => $revision->audience,
            'cta_text' => $revision->cta_text,
            'cta_url' => $revision->cta_url,
            /* The datetime-local input's format, not ISO 8601. */
            'dated_at' => $revision->dated_at?->format('Y-m-d\TH:i'),
            'authors' => $revision->author_ids ?? [],
            'categories' => $revision->category_ids ?? [],
            'note' => $revision->note,
            'is_pending' => $revision->isPending(),
            'applied_at' => $revision->applied_at?->toDateTimeString(),
            'applied_by' => $revision->appliedBy?->name,
            'created_at' => $revision->created_at?->toDateTimeString(),
            'preview_url' => $revision->isPending() ? $revision->post->url().'?v=draft' : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'authors' => Author::query()
                ->orderBy('name')
                ->get(['id', 'name', 'username']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'audiences' => [],
        ];
    }
}
