<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\Drawing\ProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(
        private ProjectService $projects,
    ) {}

    public function show(Request $request): Response
    {
        return Inertia::render('App/Settings', [
            'credits' => $this->projects->availableCredits($request->user()),
            'language' => $request->user()->language ?? 'en',
        ]);
    }

    /**
     * Profile updates: display name and interface language.
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        // `sometimes`: the language card patches without a name, and a
        // passwordless account's empty name must not block that.
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'language' => ['sometimes', Rule::in(['en', 'ru'])],
        ], [
            'name.required' => 'Name is required.',
        ]);

        $request->user()->update($validated);

        return back();
    }
}
