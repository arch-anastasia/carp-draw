<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::query()
            ->withCount(['posts as published_posts_count' => fn ($query) => $query->published()])
            ->withCount('posts')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'url' => $category->url(),
                'audience' => $category->audience,
                'posts_count' => $category->posts_count,
                'published_posts_count' => $category->published_posts_count,
            ]);

        return Inertia::render('Admin/Blog/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Blog/Categories/Create', $this->formOptions());
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::query()->create($this->attributes($request));

        return redirect()->route('admin.categories.index')->with('success', 'Category created.');
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('Admin/Blog/Categories/Edit', array_merge($this->formOptions(), [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'title' => $category->title,
                'description' => $category->description,
                'audience' => $category->audience,
                'url' => $category->url(),
            ],
        ]));
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($this->attributes($request));

        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(StoreCategoryRequest|UpdateCategoryRequest $request): array
    {
        $attributes = $request->validated();

        if (blank($attributes['slug'] ?? null)) {
            unset($attributes['slug']);
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'audiences' => [],
        ];
    }
}
