<?php

use App\Http\Controllers\Marketing\ContactController;
use App\Http\Controllers\Marketing\PageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Marketing Pages (Blade)
|--------------------------------------------------------------------------
|
| Registered last in bootstrap/app.php so every explicit path elsewhere is
| claimed first.
|
*/

Route::get('/', [PageController::class, 'home'])->name('marketing.home');
Route::get('/contact', [PageController::class, 'contact'])->name('marketing.contact');
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('marketing.contact.store');
Route::get('/privacy', [PageController::class, 'privacy'])->name('marketing.privacy');
