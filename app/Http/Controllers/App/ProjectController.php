<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Drawing\CreditLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    /**
     * The projects library: room folders plus every saved project.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('App/Projects', [
            'folders' => $user->projectFolders()
                ->orderBy('created_at')
                ->get(['id', 'name'])
                ->map(fn ($folder) => ['id' => $folder->id, 'name' => $folder->name])
                ->all(),
            'projects' => $user->projects()
                ->latest('updated_at')
                ->get()
                ->map(fn (Project $project) => WorkspaceController::card($project))
                ->values()
                ->all(),
        ]);
    }

    /**
     * Move a project between folders and/or rename it.
     */
    public function update(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'folder_id' => [
                'sometimes',
                'nullable',
                Rule::exists('project_folders', 'id')->where('user_id', $request->user()->id),
            ],
            'title' => ['sometimes', 'string', 'max:120'],
        ]);

        if (array_key_exists('folder_id', $validated)) {
            $project->project_folder_id = $validated['folder_id'];
        }

        if (array_key_exists('title', $validated)) {
            $project->title = $validated['title'];
        }

        $project->save();

        return back();
    }

    /**
     * Delete a project and its stored files.
     */
    public function destroy(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->user_id === $request->user()->id, 403);

        /*
         * A project deleted before its generation finished must hand its
         * credits back — the job's own refund contract never runs once the
         * row is gone (the ledger refund must happen while the project_id
         * still cites the spend rows). Ledger-derived, so racing the job's
         * own failure refund cannot pay twice.
         */
        if (! $project->status->isFinished()) {
            app(CreditLedger::class)->refund(
                $request->user(),
                $project,
                'Project deleted before completion',
            );
        }

        $project->purgeFiles();
        $project->delete();

        return back()->with('success', 'Project deleted.');
    }

    /**
     * Stream a project's generated output from the private disk. The local
     * disk cannot presign URLs, so this ownership-checked route is how a
     * drawing reaches an <img>.
     */
    public function image(Request $request, Project $project): StreamedResponse
    {
        abort_unless($project->user_id === $request->user()->id, 403);
        abort_unless((bool) $project->output_path, 404);

        $disk = Storage::disk($project->disk());

        abort_unless($disk->exists($project->output_path), 404);

        return $disk->response($project->output_path, null, [
            'Cache-Control' => 'private, max-age=900',
        ]);
    }
}
