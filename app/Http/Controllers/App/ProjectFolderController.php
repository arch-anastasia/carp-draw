<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ProjectFolder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectFolderController extends Controller
{
    /**
     * Create a room folder. Validation messages match the wizard's original
     * client-side copy exactly.
     */
    public function store(Request $request): RedirectResponse
    {
        $name = trim((string) $request->input('name', ''));

        if ($name === '') {
            return back()->withErrors(['name' => 'Enter a room or folder name.']);
        }

        $duplicate = $request->user()->projectFolders()
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['name' => 'A folder with this name already exists.']);
        }

        ProjectFolder::query()->create([
            'user_id' => $request->user()->id,
            'name' => mb_substr($name, 0, 120),
        ]);

        return back();
    }
}
