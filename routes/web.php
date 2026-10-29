<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\App\ProjectController;
use App\Http\Controllers\App\ProjectFolderController;
use App\Http\Controllers\App\SettingsController;
use App\Http\Controllers\App\WorkflowController;
use App\Http\Controllers\App\WorkspaceController;
use App\Http\Controllers\Billing\BillingPortalController;
use App\Http\Controllers\Billing\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authenticated App
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::prefix('app')->group(function () {
        Route::get('/', WorkspaceController::class)->name('workspace');

        // The three workflows are one page with a pinned mode.
        Route::get('/photo-to-drawing', [WorkflowController::class, 'show'])
            ->defaults('mode', 'drawing')->name('workflow.drawing');
        Route::inertia('/photo-to-model', 'Workflow/PhotoToModel')->name('workflow.photo-model');
        Route::get('/model-to-drawing', [WorkflowController::class, 'show'])
            ->defaults('mode', 'model_drawing')->name('workflow.model');
        Route::get('/create-specification', [WorkflowController::class, 'show'])
            ->defaults('mode', 'specification')->name('workflow.specification');

        // Legacy routes from the preview build.
        Route::redirect('/photo-to-3d', '/app/photo-to-model');
        Route::redirect('/drawing-to-photo', '/app/model-to-drawing');

        // Submitting a run spends credits and calls a provider — throttled
        // on top of the credit gate.
        Route::post('/workflows', [WorkflowController::class, 'store'])
            ->middleware('throttle:12,1')->name('workflow.store');

        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/{project}/status', [WorkflowController::class, 'status'])->name('projects.status');
        Route::get('/projects/{project}/image', [ProjectController::class, 'image'])->name('projects.image');
        Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
        Route::post('/folders', [ProjectFolderController::class, 'store'])->name('folders.store');

        Route::get('/plans', [SubscriptionController::class, 'index'])->name('billing.index');

        Route::get('/settings', [SettingsController::class, 'show'])->name('settings');
        Route::patch('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
    });

    Route::prefix('billing')->name('billing.')->group(function () {
        Route::post('/subscribe', [SubscriptionController::class, 'store'])->name('subscribe');
        Route::get('/success', [SubscriptionController::class, 'success'])->name('success');
        Route::post('/cancel', [SubscriptionController::class, 'destroy'])->name('cancel');
        Route::post('/resume', [SubscriptionController::class, 'resume'])->name('resume');
        Route::get('/portal', BillingPortalController::class)->name('portal');
    });

    Route::middleware('is_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::redirect('/', '/admin/users');
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    });
});
