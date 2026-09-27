<?php

use App\Http\Controllers\Api\BlogContextController;
use App\Http\Controllers\Api\BlogImageController;
use App\Http\Controllers\Api\BlogPostController;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\PinUrlRoot;
use App\Http\Middleware\ResetSeoState;
use App\Models\Post;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::bind('blogPost', function (string $value): Post {
    return Post::query()
        ->where('slug', $value)
        ->when(Str::isUuid($value), fn ($query) => $query->orWhere('id', $value))
        ->firstOrFail();
});

Route::middleware([
    PinUrlRoot::class,
    ResetSeoState::class,
    AddSecurityHeaders::class,
    'content.api',
    SubstituteBindings::class,
    'throttle:content-api',
])
    ->prefix('api/v1/blog')
    ->name('api.v1.blog.')
    ->group(function (): void {

        Route::get('context', BlogContextController::class)->name('context');

        Route::post('images', [BlogImageController::class, 'store'])->name('images.store');

        Route::controller(BlogPostController::class)->name('posts.')->group(function (): void {
            Route::get('posts', 'index')->name('index');
            Route::post('posts', 'store')->name('store');
            Route::get('posts/{blogPost}/preview', 'preview')->name('preview');
            Route::get('posts/{blogPost}', 'show')->name('show');

            Route::match(['put', 'patch'], 'posts/{blogPost}', 'update')->name('update');

            Route::delete('posts/{blogPost}', 'destroy')->name('destroy');
        });
    });
