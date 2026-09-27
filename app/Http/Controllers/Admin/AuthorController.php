<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAuthorRequest;
use App\Http\Requests\Admin\UpdateAuthorRequest;
use App\Models\Author;
use App\Models\Media;
use App\Models\User;
use App\Services\Ingest\ImageIngestor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AuthorController extends Controller
{
    public function __construct(private readonly ImageIngestor $images) {}

    public function index(): Response
    {
        $authors = Author::query()
            ->with('avatar')
            ->withCount(['posts', 'posts as published_posts_count' => fn ($query) => $query->published()])
            ->orderByDesc('profile_is_published')
            ->orderBy('name')
            ->get()
            ->map(fn (Author $author): array => [
                'id' => $author->id,
                'name' => $author->name,
                'username' => $author->username,
                'role' => $author->role,
                'profile_is_published' => $author->profile_is_published,
                'avatar_url' => $author->avatarUrl(),
                'url' => $author->authorUrl(),
                'posts_count' => $author->posts_count,
                'published_posts_count' => $author->published_posts_count,
            ]);

        return Inertia::render('Admin/Blog/Authors/Index', ['authors' => $authors]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Blog/Authors/Create', ['users' => $this->users()]);
    }

    public function store(StoreAuthorRequest $request): RedirectResponse
    {
        Author::query()->create($this->attributes($request));

        return redirect()->route('admin.authors.index')->with('success', 'Writer created.');
    }

    public function edit(Author $author): Response
    {
        return Inertia::render('Admin/Blog/Authors/Edit', [
            'author' => [
                'id' => $author->id,
                'name' => $author->name,
                'username' => $author->username,
                'role' => $author->role,
                'bio' => $author->bio,
                'avatar_url' => $author->avatar?->url,
                'website_url' => $author->website_url,
                'links' => $author->profileLinksForForm(),
                'profile_is_published' => $author->profile_is_published,
                'user_id' => $author->user_id,
                'url' => $author->authorUrl(),
                'posts_count' => $author->posts()->count(),
                'published_posts_count' => $author->posts()->published()->count(),
            ],
            'users' => $this->users(),
        ]);
    }

    public function update(UpdateAuthorRequest $request, Author $author): RedirectResponse
    {
        $author->update($this->attributes($request));

        return redirect()->route('admin.authors.index')->with('success', 'Writer updated.');
    }

    public function destroy(Author $author): RedirectResponse
    {
        if ($author->posts()->exists()) {
            return back()->withErrors(['author' => 'A writer credited on a guide cannot be deleted.']);
        }

        $author->delete();

        return redirect()->route('admin.authors.index')->with('success', 'Writer deleted.');
    }

    /** @return array<string, mixed> */
    private function attributes(StoreAuthorRequest|UpdateAuthorRequest $request): array
    {
        $attributes = $request->safe()->except(['avatar']);
        $avatar = $request->file('avatar');

        if ($avatar instanceof UploadedFile) {
            $url = $this->images->storeBytes(
                (string) file_get_contents((string) $avatar->getRealPath()),
                'avatars',
                (string) $attributes['username'],
                $avatar->extension(),
            );
            $attributes['avatar_media_id'] = Media::forUrl($url, $attributes['name'].' profile picture')?->id;
        }

        $attributes['links'] = collect($attributes['links'] ?? [])
            ->filter(fn ($link): bool => is_array($link) && filled($link['url'] ?? null))
            ->map(fn (array $link): array => [
                'label' => trim((string) ($link['label'] ?? '')),
                'url' => trim((string) $link['url']),
            ])->values()->all();

        return $attributes;
    }

    /** @return Collection<int, User> */
    private function users(): Collection
    {
        return User::query()->orderBy('name')->get(['id', 'name', 'email']);
    }
}
