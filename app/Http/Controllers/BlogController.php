<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostRevision;
use App\Services\PreviewAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class BlogController extends Controller
{
    private const RELATED_POOL_MULTIPLIER = 4;

    public function index(Request $request): View|RedirectResponse
    {
        $search = trim((string) $request->query('q', ''));

        /*
         * This is a curated magazine, not a feed: there is no second page of
         * Start Here. Retire every spelling of that duplicate URL with a 301,
         * while leaving a real search's pagination to its own branch below.
         */
        if ($search === '' && $request->query->has('page')) {
            return redirect()->route('marketing.blog.index', [], 301);
        }

        $categories = $this->categoriesWithPosts();

        if ($search !== '') {
            $posts = Post::query()
                ->published()
                ->with(['authors', 'categories'])
                ->where(function ($query) use ($search): void {
                    $query->whereLike('title', "%{$search}%")
                        ->orWhereLike('excerpt', "%{$search}%");
                })
                ->latest('published_at')
                ->paginate(9)
                ->withQueryString();

            return view('marketing.blog.index', [
                'posts' => $posts,
                'schemaPosts' => $posts,
                'startHere' => collect(),
                'sections' => collect(),
                'unpublished' => $this->unpublished($request, $search),
                'categories' => $categories,
                'authors' => Author::query()->publishedProfiles()->orderBy('name')->get(),
                'search' => $search,
            ]);
        }

        $startHere = $this->startHere();
        $sections = $this->sections($categories);

        return view('marketing.blog.index', [
            'posts' => collect(),
            'schemaPosts' => $startHere
                ->merge($sections->flatMap(fn (array $section) => $section['posts']))
                ->unique('id')
                ->sortByDesc('published_at')
                ->values(),
            'startHere' => $startHere,
            'sections' => $sections,
            'unpublished' => $this->unpublished($request, $search),
            'categories' => $categories,
            'authors' => Author::query()->publishedProfiles()->orderBy('name')->get(),
            'search' => $search,
        ]);
    }

    /**
     * Work in flight, for the admin looking at the listing. Empty for everyone
     * else, so the template asks one question rather than two.
     *
     * Its own row above the fold rather than mixed into the category sections:
     * a draft slotted into "For men" would be counted by that row's "View all
     * N" link and pushed a real guide off the end of it, so the page an admin
     * checks would stop being the page a reader gets. One row, and the rest of
     * the listing stays honest.
     *
     * A search narrows this row by the same terms as the results under it.
     * Someone who typed "fades" is asking about fades, and answering with
     * every draft on the desk is the row shouting over the question.
     *
     * @return Collection<int, Post>
     */
    private function unpublished(Request $request, string $search = ''): Collection
    {
        if (! app(PreviewAccess::class)->isAdmin($request)) {
            return collect();
        }

        return collect(Post::query()
            ->unpublished()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->whereLike('title', "%{$search}%")
                    ->orWhereLike('excerpt', "%{$search}%");
            }))
            ->with(['authors', 'categories'])
            /*
             * Drafts have no publish date to sort on, so the order is the one
             * the desk works in: scheduled posts by when they land, then drafts
             * by when they were last touched.
             */
            ->orderByRaw('published_at is null')
            ->orderBy('published_at')
            ->latest('updated_at')
            ->get()
            ->all());
    }

    /**
     * The hand-picked opening row: where to send a reader who has arrived with
     * no particular question. Ordered by config, not by date - that is the
     * point of it. Callers keep this off a search results page, where a
     * curated row on top of a filtered list would be answering a question
     * nobody asked.
     *
     * @return Collection<int, Post>
     */
    private function startHere(): Collection
    {
        $slugs = (array) config('blog.start_here', []);

        if ($slugs === []) {
            return collect();
        }

        return Post::query()
            ->published()
            ->whereIn('slug', $slugs)
            ->with(['authors', 'categories'])
            ->get()
            ->sortBy(fn (Post $post): int => array_search($post->slug, $slugs, true))
            ->values();
    }

    /**
     * One row per category - its latest few guides and a "View all" link to
     * its hub - plus a trailing catch-all for anything filed nowhere, so a
     * post is never invisible just for lacking a category. That catch-all has
     * no hub behind it, so it must carry every guide rather than a teaser.
     * The catalog is small enough that a query per row costs nothing and
     * stays far simpler than a top-N-per-group join.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Category>  $categories
     */
    private function sections(Collection $categories, int $perSection = 3): Collection
    {
        /** @var Collection<int, array{category: ?Category, label: string, url: ?string, total: int, posts: \Illuminate\Database\Eloquent\Collection<int, Post>}> $sections */
        $sections = $categories->map(function (Category $category) use ($perSection): array {
            $query = Post::query()
                ->published()
                ->whereHas('categories', fn ($query) => $query->whereKey($category->id));

            return [
                'category' => $category,
                'label' => $category->name,
                'url' => $category->url(),
                'total' => (clone $query)->count(),
                'posts' => $query->with(['authors', 'categories'])
                    ->latest('published_at')
                    ->limit($perSection)
                    ->get(),
            ];
        });

        $uncategorized = Post::query()
            ->published()
            ->doesntHave('categories')
            ->with(['authors', 'categories'])
            ->latest('published_at')
            ->get();

        if ($uncategorized->isNotEmpty()) {
            $sections->push([
                'category' => null,
                'label' => 'More guides',
                'url' => null,
                'total' => $uncategorized->count(),
                'posts' => $uncategorized,
            ]);
        }

        return collect($sections->all());
    }

    public function entry(Request $request, string $slug): View
    {
        $category = Category::query()->where('slug', $slug)->first();

        if ($category instanceof Category) {
            return $this->category($category);
        }

        $post = Post::query()->where('slug', $slug)->first();
        abort_if($post?->isKilled(), 410);

        $access = app(PreviewAccess::class);
        $mayPreview = $access->granted($request);

        abort_if($post === null || (! $post->isPublished() && ! $mayPreview), 404);

        if ($request->query('v') === 'draft') {
            $revision = $mayPreview ? $post->pendingRevision() : null;

            /*
             * 404 rather than quietly serving the live wording: a preview link
             * that silently shows the current article is how a rewrite gets
             * approved without anyone having read it.
             */
            abort_if($revision === null, 404);

            return $this->renderPost($revision->asPost(), $revision);
        }

        return $this->renderPost($post);
    }

    public function authors(): View
    {
        $authors = Author::query()
            ->publishedProfiles()
            ->withCount(['posts' => fn ($query) => $query->published()])
            ->orderByDesc('posts_count')
            ->orderBy('name')
            ->get();

        return view('marketing.blog.authors', [
            'authors' => $authors,
        ]);
    }

    public function author(string $username): View
    {
        $author = Author::query()
            ->publishedProfiles()
            ->where('username', $username)
            ->first();

        abort_if($author === null, 404);

        return view('marketing.blog.author', [
            'author' => $author,
            'posts' => Post::query()
                ->published()
                ->whereHas('authors', fn ($query) => $query->whereKey($author->id))
                ->with(['authors', 'categories'])
                ->latest('published_at')
                ->paginate(9),
        ]);
    }

    public function renderPost(Post $post, ?PostRevision $revision = null): View
    {
        /*
         * A revision preview arrives with its relations already set from the
         * id lists it holds - loading here would replace them with whatever
         * the live article is filed under, which is the one thing the preview
         * must not show.
         */
        if ($revision === null) {
            $post->load(['authors', 'categories']);
        }

        /*
         * Loaded either way, unlike the byline and the filing above: the cut
         * the offer demonstrates is the article's, not the draft's, and a
         * revision replicates the column rather than carrying its own.
         */

        /*
         * Editorial reporting only, and cheap enough to do inline: a counter
         * bump is one UPDATE with no read-modify-write behind it.
         *
         * Timestamps off for it. Eloquent adds `updated_at` to an increment,
         * so counting a view was re-dating the article: the byline said
         * "Updated today", the Article schema said dateModified was today, and
         * /sitemap.xml reported every URL as changed on every crawl. A reader
         * is not an editor.
         *
         * Not counted at all while the article is unpublished, nor when what
         * is being read is a pending rewrite of a live one. The only people
         * who can reach either are checking their own work, and a guide that
         * goes live already reporting readers it never had - or one whose
         * count climbs every time a draft is proofread - is a number nobody
         * can use.
         *
         * Nor an admin's own read of a live one, for the same reason one step
         * further: this page is where articles are written now, and a count
         * that says how a piece is doing with readers must not be moved by the
         * fifteen reloads it took to write it.
         */
        if ($post->isPublished() && $revision === null && ! request()->is('api/*') && ! app(PreviewAccess::class)->granted(request())) {
            $post->timestamps = false;
            $post->increment('views_count');
            $post->timestamps = true;
        }

        return view('marketing.blog.post', [
            'post' => $post,
            'related' => $this->related($post),
            'toolPage' => null,
            'revision' => $revision,
            /*
             * Whether this page is also the editor. An admin writes the article
             * on the article - see resources/js/blog/inlineEditor.ts - which is
             * the same argument the preview already makes: the question "does
             * this read right" is only answerable on the page a reader gets, so
             * that is where it should be answerable to fix it too.
             *
             * Never while a pending rewrite is being previewed. What is on
             * screen then is the revision's wording on a model that was never
             * stored, and typing into it would write those words onto the live
             * article - approving a rewrite by editing a preview of it.
             */
            'editable' => $revision === null && app(PreviewAccess::class)->isAdmin(request()),
        ]);
    }

    private function category(Category $category): View
    {
        return view('marketing.blog.category', [
            'category' => $category,
            'posts' => Post::query()
                ->published()
                ->whereHas('categories', fn ($query) => $query->whereKey($category->id))
                ->with(['authors', 'categories'])
                ->latest('published_at')
                ->get(),
            'toolPage' => null,
            'categories' => $this->categoriesWithPosts(),
        ]);
    }

    /**
     * The articles printed under a post.
     *
     * Same category only, because that is what "keep reading" means to someone
     * who just read about fades - a rail topped up with whatever is newest is
     * how a blog turns its own footer into noise. A post filed nowhere falls
     * back to the newest guides, which is the only honest answer for it.
     *
     * Inside that, the reader's own side of the catalog leads: someone who
     * just read a women's guide is offered women's guides first, and the other
     * side only fills the places those could not. A rail is three slots, and
     * spending them on the wrong half of the catalog reads as a site that
     * was not paying attention.
     *
     * The side comes from Post::resolvedAudience(), which follows the category
     * a post inherits from as well as the one it declares - a rule SQL cannot
     * see through the pivot, hence a bounded pool sorted in PHP.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Post>
     */
    private function related(Post $post, int $limit = 3): \Illuminate\Database\Eloquent\Collection
    {
        $candidates = Post::query()
            ->published()
            ->whereKeyNot($post->id)
            ->when(
                $post->categories->isNotEmpty(),
                fn ($query) => $query->whereHas(
                    'categories',
                    fn ($query) => $query->whereIn('categories.id', $post->categories->pluck('id'))
                )
            )
            ->with(['authors', 'categories'])
            ->latest('published_at')
            ->limit($limit * self::RELATED_POOL_MULTIPLIER)
            ->get();

        $audience = $post->resolvedAudience();

        if ($audience === null) {
            return $candidates->take($limit)->values();
        }

        /*
         * A partition, not a ranking: everything matching keeps its date order
         * and so does everything else, because PHP 8 sorts are stable. Newest
         * first still holds on both halves of the rail.
         */
        return $candidates
            ->sortBy(fn (Post $candidate): int => $candidate->resolvedAudience() === $audience ? 0 : 1)
            ->take($limit)
            ->values();
    }

    /**
     * Chips only for categories that lead somewhere. An empty hub in the
     * filter row is a dead end the reader has to discover by clicking.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Category>
     */
    private function categoriesWithPosts()
    {
        return Category::query()
            ->whereHas('posts', function (Builder $query): void {
                /** @var Builder<Post> $query */
                $query->published();
            })
            ->orderBy('name')
            ->get();
    }
}
