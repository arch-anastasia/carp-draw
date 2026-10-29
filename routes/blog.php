<?php

use App\Http\Controllers\Admin\AuthorController as AdminAuthorController;
use App\Http\Controllers\Admin\BlogImageController as AdminBlogImageController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\PostController as AdminPostController;
use App\Http\Controllers\Admin\PostRevisionController as AdminPostRevisionController;
use App\Http\Controllers\BlogController;
use Illuminate\Support\Facades\Route;

Route::controller(BlogController::class)->prefix('blog')->name('marketing.blog.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('authors', 'authors')->name('authors');
    Route::get('authors/{username}', 'author')->name('author');
    Route::get('{slug}', 'entry')->name('entry');
});

Route::middleware(['auth', 'is_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::controller(AdminPostController::class)->name('posts.')->group(function () {
        Route::get('posts', 'index')->name('index');
        Route::get('posts/create', 'create')->name('create');
        Route::post('posts', 'store')->name('store');
        /*
         * Bound by id, not the model's route key: a post's route key is its
         * slug because that is its public URL, and an admin editing the slug
         * would otherwise be renaming the page they are standing on.
         */
        Route::get('posts/{post:id}/edit', 'edit')->name('edit');
        Route::put('posts/{post:id}', 'update')->name('update');
        Route::delete('posts/{post:id}', 'destroy')->name('destroy');

        /*
         * Publishing from the article's own page. The preview an admin reads
         * at /blog/{slug} is the real template, so the button that ends the
         * preview lives there and posts back here - the authorisation for it
         * belongs with the rest of the desk rather than in the public
         * controller. It redirects to the article, which is where the person
         * who clicked it is standing.
         */
        Route::post('posts/{post:id}/publish', 'publish')->name('publish');
        Route::post('posts/{post:id}/kill', 'kill')->name('kill');
        Route::post('posts/{post:id}/unkill', 'unkill')->name('unkill');

        /*
         * What the editor on an article's own page saves through: the same
         * fields as `update` above, any subset of them, answering with JSON
         * instead of a redirect. The form and the page are two doors onto one
         * article. See resources/js/blog/inlineEditor.ts.
         */
        Route::patch('posts/{post:id}/inline', 'updateInline')->name('inline.update');
    });
    Route::controller(AdminPostRevisionController::class)->name('posts.revisions.')->group(function () {
        Route::post('posts/{post:id}/versions', 'store')->name('store');
        Route::get('posts/{post:id}/versions/{revision:id}/edit', 'edit')->name('edit');
        Route::put('posts/{post:id}/versions/{revision:id}', 'update')->name('update');
        Route::get('posts/{post:id}/versions/{revision:id}/changes', 'diff')->name('diff');
        Route::post('posts/{post:id}/versions/{revision:id}/apply', 'apply')->name('apply');
        Route::delete('posts/{post:id}/versions/{revision:id}', 'destroy')->name('destroy');
    });
    Route::controller(AdminAuthorController::class)->name('authors.')->group(function () {
        Route::get('authors', 'index')->name('index');
        Route::get('authors/create', 'create')->name('create');
        Route::post('authors', 'store')->name('store');
        Route::get('authors/{author:id}/edit', 'edit')->name('edit');
        Route::put('authors/{author:id}', 'update')->name('update');
        Route::delete('authors/{author:id}', 'destroy')->name('destroy');
    });
    Route::controller(AdminCategoryController::class)->name('categories.')->group(function () {
        Route::get('categories', 'index')->name('index');
        Route::get('categories/create', 'create')->name('create');
        Route::post('categories', 'store')->name('store');
        Route::get('categories/{category:id}/edit', 'edit')->name('edit');
        Route::put('categories/{category:id}', 'update')->name('update');
        Route::delete('categories/{category:id}', 'destroy')->name('destroy');
    });
    Route::post('blog-images', [AdminBlogImageController::class, 'store'])->name('blog-images.store');
});

if (config('blog.indexnow.enabled') && preg_match('/^[a-zA-Z0-9-]{8,128}$/', (string) config('blog.indexnow.key'))) {
    Route::get('/'.config('blog.indexnow.key').'.txt', fn () => response((string) config('blog.indexnow.key'))->header('Content-Type', 'text/plain'));
}
