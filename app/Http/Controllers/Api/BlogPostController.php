<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BlogController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBlogPostRequest;
use App\Http\Requests\Api\UpdateBlogPostRequest;
use App\Http\Resources\BlogPostResource;
use App\Models\Author;
use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BlogPostController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::in(['published', 'scheduled', 'draft', 'killed'])],
            'search' => ['nullable', 'string', 'max:500'],
            'keyword' => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:200'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);
        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('search', ''));
        $category = trim((string) $request->query('category', ''));
        $keyword = trim((string) $request->query('keyword', ''));

        $blogPosts = Post::query()
            ->with(['authors', 'categories', 'cover'])
            ->when($search !== '', fn ($query) => $query->where(
                fn ($query) => $query->whereLike('title', "%{$search}%")
                    ->orWhereLike('excerpt', "%{$search}%")
                    ->orWhereLike('body', "%{$search}%")
            ))
            ->when($keyword !== '', fn ($query) => $query->where(
                fn ($query) => $query->whereRaw('LOWER(target_keyword) = LOWER(?)', [$keyword])
                    ->orWhere(fn (Builder $query) => $this->whereSecondaryKeywordMatches($query, $keyword))
            ))
            ->when($category !== '', fn ($query) => $query->whereHas(
                'categories',
                fn ($query) => $query->where('slug', $category)
            ))
            ->when($status === 'published', fn ($query) => $query->published())
            ->when($status === 'scheduled', fn ($query) => $query->whereNull('killed_at')->whereNotNull('published_at')->where('published_at', '>', now()))
            ->when($status === 'draft', fn ($query) => $query->whereNull('killed_at')->whereNull('published_at'))
            ->when($status === 'killed', fn ($query) => $query->killed())
            ->latest('created_at')
            ->paginate(min(100, max(1, (int) $request->query('per_page', 20))))
            ->withQueryString();

        return BlogPostResource::collection($blogPosts);
    }

    public function show(Post $blogPost): BlogPostResource
    {
        return new BlogPostResource($blogPost->load(['authors', 'categories', 'cover']));
    }

    private function whereSecondaryKeywordMatches(Builder $query, string $keyword): void
    {
        if ($query->getModel()->getConnection()->getDriverName() === 'pgsql') {
            $query->whereRaw(
                "EXISTS (SELECT 1 FROM jsonb_array_elements_text(COALESCE(secondary_keywords::jsonb, '[]'::jsonb)) AS secondary_keyword WHERE LOWER(secondary_keyword) = LOWER(?))",
                [$keyword],
            );

            return;
        }

        $query->whereRaw(
            "EXISTS (SELECT 1 FROM json_each(COALESCE(secondary_keywords, '[]')) AS secondary_keyword WHERE LOWER(secondary_keyword.value) = LOWER(?))",
            [$keyword],
        );
    }

    public function preview(BlogController $blog, Post $blogPost): Response
    {
        return response($blog->renderPost($blogPost))
            ->header('X-Robots-Tag', 'noindex');
    }

    public function store(StoreBlogPostRequest $request): JsonResponse
    {
        $blogPost = DB::transaction(function () use ($request): Post {
            $post = Post::query()->create($this->attributes($request));
            $this->syncRelations($post, $request);

            return $post;
        });

        return (new BlogPostResource($blogPost->load(['authors', 'categories', 'cover'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateBlogPostRequest $request, Post $blogPost): BlogPostResource
    {
        DB::transaction(function () use ($request, $blogPost): void {
            $blogPost->update($this->attributes($request));
            $this->syncRelations($blogPost, $request);
        });

        return new BlogPostResource($blogPost->load(['authors', 'categories', 'cover']));
    }

    public function destroy(Post $blogPost): JsonResponse
    {
        $blogPost->update(['published_at' => null]);

        return (new BlogPostResource($blogPost->load(['authors', 'categories', 'cover'])))
            ->additional([
                'message' => $blogPost->isKilled()
                    ? 'Post remains killed. Explicitly un-kill it before publishing.'
                    : 'Post delisted. It is a draft again - nothing was deleted, and setting published_at puts it back.',
            ])
            ->response();
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(StoreBlogPostRequest|UpdateBlogPostRequest $request): array
    {
        $attributes = $request->safe()->except(['authors', 'categories', 'cover_image', 'cover_image_alt', 'look']);

        /*
         * An empty slug means "derive it from the title", not "store an empty
         * string" - which would collide with every other post that left it
         * blank. Post::creating() does the deriving.
         */
        if (blank($attributes['slug'] ?? null)) {
            unset($attributes['slug']);
        }

        /*
         * The payload carries a URL, which is the only thing a drafting agent
         * knows about an image - the one it uploaded to /api/v1/blog/images, or
         * one already on the site. Storage is a media row, so it is resolved
         * here rather than making the caller learn about media ids, and the alt
         * text rides along because it describes that row rather than this post.
         */
        if ($request->safe()->has('cover_image')) {
            $alt = $request->safe()->has('cover_image_alt')
                ? $request->safe()->string('cover_image_alt')->toString()
                : null;

            $attributes['cover_media_id'] = Media::forUrl($request->safe()->string('cover_image')->toString(), $alt)?->id;
        }

        /*
         * The cut the offer beside the article demonstrates, by catalog key
         * for the same reason the byline arrives by username: an agent can
         * reproduce a key it read in the context document, and cannot
         * reproduce an id. Null clears it back to the default offer.
         *
         * The key was already checked against the demonstrable set by the
         * request, so a miss here means the row was retired between the two
         * lines - and falling back to the default offer is the right answer
         * to that anyway.
         */

        return $attributes;
    }

    private function syncRelations(Post $blogPost, StoreBlogPostRequest|UpdateBlogPostRequest $request): void
    {
        $blogPost->syncAuthors($this->authorIds($request->safe()->array('authors')));

        $blogPost->categories()->sync($this->categoryIds($request->safe()->array('categories')));
    }

    /**
     * Usernames to author ids, keeping the order they were sent in - the byline is
     * printed in that order, so "first name first" has to survive the lookup.
     * An empty list is house copy; Post::syncAuthors() handles that.
     *
     * @param  array<int, mixed>  $usernames
     * @return array<int, string>
     */
    private function authorIds(array $usernames): array
    {
        $usernames = collect($usernames)->filter(fn (mixed $username): bool => is_string($username) && trim($username) !== '')
            ->map(fn (string $username): string => trim($username));

        if ($usernames->isEmpty()) {
            return [];
        }

        $byUsername = Author::query()
            ->whereIn('username', $usernames->all())
            ->pluck('id', 'username');

        return $usernames->map(fn (string $username): ?string => $byUsername[$username] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Category slugs to ids. Unordered - a post's categories are a set, and
     * `primaryCategory()` reads the first row back by insertion, not by rank.
     *
     * @param  array<int, mixed>  $slugs
     * @return array<int, string>
     */
    private function categoryIds(array $slugs): array
    {
        $slugs = collect($slugs)->filter(fn (mixed $slug): bool => is_string($slug) && trim($slug) !== '')
            ->map(fn (string $slug): string => trim($slug));

        if ($slugs->isEmpty()) {
            return [];
        }

        return Category::query()
            ->whereIn('slug', $slugs->all())
            ->pluck('id')
            ->all();
    }
}
