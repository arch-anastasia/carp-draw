<?php

namespace App\Http\Requests\App;

use App\Enums\WorkflowMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The server re-validates what the wizard already told the visitor:
     * per-mode extension lists and size caps from config/drawing.php, and
     * every select against its closed list — the style value reaches the
     * generation prompt, and arbitrary strings are instructions we didn't
     * write.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $mode = $this->mode();
        $rules = $mode->uploadRules();

        return [
            'mode' => ['required', Rule::in(['drawing', 'modelDrawing', 'specification'])],
            /*
             * Extension-checked, not content-sniffed: Laravel's mimes rule
             * guesses from file CONTENT, and CAD formats (.skp/.3ds/.obj/
             * .dwg/.dxf) sniff as generic binary/text — a content rule
             * rejected every format the mode advertises. This mirrors the
             * wizard's own client-side check; unusable bytes are caught by
             * the provider and refunded.
             */
            'file' => [
                'required',
                'file',
                'max:'.($rules['max_mb'] * 1024),
                function (string $attribute, mixed $value, \Closure $fail) use ($rules): void {
                    $extension = strtolower((string) $value->getClientOriginalExtension());

                    if (! in_array($extension, $rules['extensions'], true)) {
                        $fail('Supported formats: '.strtoupper(implode(', ', $rules['extensions'])).'.');
                    }
                },
            ],
            ...match ($mode) {
                WorkflowMode::Drawing => [
                    'width' => ['required', 'numeric', 'gt:0'],
                    'height' => ['required', 'numeric', 'gt:0'],
                    'depth' => ['nullable', 'numeric', 'gt:0'],
                    'unit' => ['required', Rule::in(['mm', 'cm', 'in'])],
                    'style' => ['required', Rule::in(array_keys(config('drawing.prompts.standards', [])))],
                ],
                WorkflowMode::ModelDrawing => [
                    'view_set' => ['required', Rule::in(array_keys(config('drawing.prompts.view_sets', [])))],
                    'model_units' => ['required', Rule::in(['mm', 'cm', 'in'])],
                ],
                WorkflowMode::Specification => [
                    'project_name' => ['required', 'string', 'max:120'],
                    'material' => ['nullable', 'string', 'max:120'],
                    'specification_type' => ['required', Rule::in([
                        'manufacturing specification',
                        'materials and finishes schedule',
                        'project handover document',
                    ])],
                ],
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $mode = $this->mode();
        $rules = $mode->uploadRules();

        return [
            'file.required' => 'Choose a file before continuing.',
            'file.max' => "The file is larger than {$rules['max_mb']} MB.",
            'width.required' => 'Add valid width and height measurements.',
            'height.required' => 'Add valid width and height measurements.',
            'width.gt' => 'Add valid width and height measurements.',
            'height.gt' => 'Add valid width and height measurements.',
            'project_name.required' => 'Add a project or product name.',
        ];
    }

    public function mode(): WorkflowMode
    {
        return WorkflowMode::fromClient((string) $this->input('mode', 'drawing')) ?? WorkflowMode::Drawing;
    }

    /**
     * The wizard's step-2 answers, trimmed to the mode's own fields.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $validated = $this->validated();

        unset($validated['mode'], $validated['file']);

        return $validated;
    }
}
