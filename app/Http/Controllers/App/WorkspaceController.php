<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    /**
     * The workspace home: the three creation cards plus recent projects.
     */
    public function __invoke(Request $request): Response
    {
        $recent = $request->user()->projects()
            ->latest('updated_at')
            ->limit(4)
            ->get()
            ->map(fn (Project $project) => $this->card($project))
            ->values()
            ->all();

        return Inertia::render('App/Home', [
            'recentProjects' => $recent,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function card(Project $project): array
    {
        return [
            'id' => $project->id,
            'title' => $project->title,
            'type_label' => $project->workflow->typeLabel(),
            'updated_human' => 'Updated '.$project->updatedHuman(),
            'image_url' => $project->output_path ? route('projects.image', $project) : null,
            'folder_id' => $project->project_folder_id,
            'workflow' => $project->workflow->clientValue(),
        ];
    }
}
