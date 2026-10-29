<?php

namespace App\Http\Controllers\App;

use App\Enums\WorkflowMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\App\StoreWorkflowRequest;
use App\Models\Project;
use App\Services\Drawing\DrawingFailed;
use App\Services\Drawing\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function __construct(
        private ProjectService $projects,
    ) {}

    /**
     * The wizard page — one component, three modes. The mode arrives as a
     * route default (each pretty URL pins its own), never from user input.
     */
    public function show(Request $request, string $mode): Response
    {
        $mode = WorkflowMode::from($mode);

        return Inertia::render('Workflow/Show', [
            'mode' => $mode->clientValue(),
            'credits' => $this->projects->availableCredits($request->user()),
            'cost' => $mode->cost(),
            'canModelDrawing' => $this->projects->canModelDrawing($request->user()),
        ]);
    }

    /**
     * Charge and queue one workflow run. JSON — the wizard submits with
     * fetch and then polls the project's status.
     */
    public function store(StoreWorkflowRequest $request): JsonResponse
    {
        $mode = $request->mode();

        try {
            $project = $this->projects->create(
                $request->user(),
                $mode,
                $request->file('file'),
                $request->settings(),
            );
        } catch (DrawingFailed $e) {
            $status = match ($e->errorCode) {
                'insufficient_credits' => 402,
                'plan_required' => 403,
                default => 422,
            };

            return response()->json([
                'error' => $e->errorCode,
                'message' => $e->visitorMessage(),
            ], $status);
        }

        return response()->json(['project' => $project->id]);
    }

    /**
     * The wizard's poll target.
     */
    public function status(Request $request, Project $project): JsonResponse
    {
        abort_unless($project->user_id === $request->user()->id, 403);

        return response()->json($this->projects->statusPayload($project));
    }
}
