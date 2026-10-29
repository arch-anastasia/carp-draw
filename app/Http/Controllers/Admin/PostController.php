<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KillPostRequest;
use App\Http\Requests\Admin\PublishPostRequest;
use App\Http\Requests\Admin\StorePostRequest;
use App\Http\Requests\Admin\UpdatePostInlineRequest;
use App\Http\Requests\Admin\UpdatePostRequest;
use App\Models\Author;
use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostRevision;
use App\Services\BlogEditorContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $posts = Post::query()
            ->when($status === 'killed', fn ($query) => $query->killed(), fn ($query) => $query->whereNull('killed_at'))
            ->with(['authors:id,name', 'categories:id,name'])
            ->when($search, fn ($query) => $query->whereLike('title', "%{$search}%"))
            ->when($status === 'published', fn ($query) => $query->published())
            ->when($status === 'scheduled', fn ($query) => $query->whereNotNull('published_at')->where('published_at', '>', now()))
            ->when($status === 'draft', fn ($query) => $query->whereNull('published_at'))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Post $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'url' => $post->url(),
                'status' => $this->status($post),
                'published_at' => $post->published_at?->toDateTimeString(),
                'views_count' => $post->views_count,
                'reading_minutes' => $post->reading_minutes,
                'authors' => $post->authors->pluck('name')->all(),
                'categories' => $post->categories->pluck('name')->all(),
            ]);

        return Inertia::render('Admin/Blog/Posts/Index', [
            'posts' => $posts,
            'filters' => ['search' => $search, 'status' => $status],
            'stats' => [
                'total' => Post::query()->count(),
                'published' => Post::query()->published()->count(),
                'drafts' => Post::query()->whereNull('killed_at')->whereNull('published_at')->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Blog/Posts/Create', $this->formOptions());
    }

    public function store(StorePostRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $post = Post::query()->create($this->attributes($request));
            $post->syncAuthors($request->validated('authors') ?? []);
            $post->categories()->sync($request->validated('categories') ?? []);
        });

        return redirect()->route('admin.posts.index')->with('success', 'Post created.');
    }

    public function edit(Post $post): Response
    {
        $post->load(['authors:id', 'categories:id', 'revisions.appliedBy:id,name']);

        return Inertia::render('Admin/Blog/Posts/Edit', array_merge($this->formOptions(), [
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'authors' => $post->authors->pluck('id')->all(),
                'excerpt' => $post->excerpt,
                'body' => $post->body,
                'cover_image_path' => $post->cover?->url,
                'meta_title' => $post->meta_title,
                'meta_description' => $post->meta_description,
                'audience' => $post->audience,
                'cta_text' => $post->cta_text,
                'cta_url' => $post->cta_url,
                /* The datetime-local input's format, not ISO 8601. */
                'published_at' => $post->published_at?->format('Y-m-d\TH:i'),
                'killed_at' => $post->killed_at?->toIso8601String(),
                'killed_reason' => $post->killed_reason,
                'dated_at' => $post->dated_at?->format('Y-m-d\TH:i'),
                'categories' => $post->categories->pluck('id')->all(),
                'url' => $post->url(),
                'status' => $this->status($post),
                'views_count' => $post->views_count,
            ],
            /*
             * The article's rewrites: the one waiting for approval, and every
             * one that has landed. Listed on the editor rather than a screen of
             * their own, because "what is being rewritten right now" is a
             * question about this article and nowhere else.
             */
            'versions' => $post->revisions->map(fn (PostRevision $revision) => [
                'id' => $revision->id,
                'is_pending' => $revision->isPending(),
                'note' => $revision->note,
                'created_at' => $revision->created_at?->toDayDateTimeString(),
                'applied_at' => $revision->applied_at?->toDayDateTimeString(),
                'applied_by' => $revision->appliedBy?->name,
            ])->all(),
        ]));
    }

    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        DB::transaction(function () use ($request, $post): void {
            $post->update($this->attributes($request));
            $post->syncAuthors($request->validated('authors') ?? []);
            $post->categories()->sync($request->validated('categories') ?? []);
        });

        return redirect()->route('admin.posts.index')->with('success', 'Post updated.');
    }

    public function publish(PublishPostRequest $request, Post $post): RedirectResponse
    {
        if ($post->isPublished()) {
            return redirect()->to($post->url());
        }

        $publishAt = $post->publishableAt();
        $post->update(['published_at' => $publishAt]);

        return redirect()->to($post->url())->with('success', $publishAt->isFuture()
            ? 'Scheduled for '.$publishAt->format('j F Y').'.'
            : 'Published.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();

        return redirect()->route('admin.posts.index')->with('success', 'Post deleted.');
    }

    public function kill(KillPostRequest $request, Post $post): RedirectResponse
    {
        $post->update([
            'killed_at' => $post->killed_at ?? now(),
            'killed_reason' => $request->validated('killed_reason'),
        ]);

        return redirect()->route('admin.posts.edit', $post->id)->with('success', 'Post killed. Its public URL now returns 410 Gone.');
    }

    public function unkill(KillPostRequest $request, Post $post): RedirectResponse
    {
        if ($post->isKilled()) {
            $post->update(['killed_at' => null, 'killed_reason' => null, 'published_at' => null]);
        }

        return redirect()->route('admin.posts.edit', $post->id)->with('success', 'Post un-killed. It must be published separately.');
    }

    public function updateInline(UpdatePostInlineRequest $request, Post $post, BlogEditorContext $context): JsonResponse
    {
        DB::transaction(function () use ($request, $post): void {
            $post->update($this->attributes($request));

            if ($request->has('authors')) {
                $post->syncAuthors($request->validated('authors') ?? []);
            }

            if ($request->has('categories')) {
                $post->categories()->sync($request->validated('categories') ?? []);
            }
        });

        $post->refresh();

        return response()->json([
            'post' => $context->post($post),
            'html' => $post->editableBody(),
            'outline' => $context->outline($post),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(StorePostRequest|UpdatePostRequest|UpdatePostInlineRequest $request): array
    {
        /*
         * `faq` is not a column. The inline editor sends the questions
         * separately because that is how the page shows them, and the request
         * has already folded them into the body by the time this runs.
         */
        $attributes = $request->safe()->except(['authors', 'categories', 'faq']);

        /*
         * An empty slug field means "derive it", not "store an empty string" -
         * which would collide with every other post that left it blank.
         */
        if (blank($attributes['slug'] ?? null)) {
            unset($attributes['slug']);
        }

        /*
         * The form asks for a cover URL, which is the right question to put to
         * an editor. Storage is a media row, so the URL is resolved here rather
         * than making the form know about media ids.
         */
        if (array_key_exists('cover_image_path', $attributes)) {
            $media = Media::forUrl($attributes['cover_image_path']);
            if ($media !== null && array_key_exists('cover_image_alt', $attributes)) {
                $media->update(['alt' => $attributes['cover_image_alt']]);
            }
            $attributes['cover_media_id'] = $media?->id;

            unset($attributes['cover_image_path']);
        }

        unset($attributes['cover_image_alt']);

        return $attributes;
    }

    private function status(Post $post): string
    {
        return match (true) {
            $post->isKilled() => 'killed',
            $post->isPublished() => 'published',
            $post->isScheduled() => 'scheduled',
            default => 'draft',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'authors' => $this->authorsList(),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'audiences' => [],
        ];
    }

    /**
     * Every author row is creditable; profile publication is an independent
     * public-page decision and never removes a name from the editor.
     *
     * @return Collection<int, Author>
     */
    private function authorsList(): Collection
    {
        return Author::query()
            ->orderBy('name')
            ->get(['id', 'name', 'username']);
    }
}
