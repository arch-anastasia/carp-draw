<?php

namespace App\Services\Drawing;

use App\Enums\WorkflowMode;
use App\Models\Project;

/**
 * Assembles the generation prompt for a project.
 *
 * The visual rules live in config/drawing.php (`prompts.style` — the White
 * Technical Furniture standard); this class owns only sentence order and the
 * identity guard. Nothing from the visitor reaches the prompt except values
 * validated against closed lists and the numeric dimensions.
 */
class PromptBuilder
{
    /**
     * The rule that makes it an edit rather than an invention: the output
     * must show THE piece in the source, not a plausible piece like it.
     */
    private const IDENTITY_GUARD = 'Draw exactly the piece of furniture shown in the source image — '
        .'same proportions, same construction, same panel layout, same details. '
        .'Do not invent furniture, do not add or remove elements, do not change the design.';

    public function buildFor(Project $project): string
    {
        $settings = $project->settings ?? [];

        $parts = match ($project->workflow) {
            WorkflowMode::Drawing => $this->forDrawing($settings),
            WorkflowMode::ModelDrawing => $this->forModelDrawing($settings),
            WorkflowMode::Specification => $this->forSpecification($settings),
        };

        return implode(' ', array_filter([
            'Convert this furniture reference into a technical drawing sheet.',
            ...$parts,
            (string) config('drawing.prompts.style'),
            self::IDENTITY_GUARD,
        ]));
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, string>
     */
    private function forDrawing(array $settings): array
    {
        $standard = (string) ($settings['style'] ?? 'clean orthographic set');
        $standardBrief = (string) config("drawing.prompts.standards.{$standard}",
            (string) config('drawing.prompts.standards.clean orthographic set'));

        return [
            $standardBrief,
            $this->dimensionSentence($settings),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, string>
     */
    private function forModelDrawing(array $settings): array
    {
        $viewSet = (string) ($settings['view_set'] ?? 'front side and plan views');
        $views = (string) config("drawing.prompts.view_sets.{$viewSet}",
            (string) config('drawing.prompts.view_sets.front side and plan views'));

        $units = (string) ($settings['model_units'] ?? 'mm');

        return [
            "Produce a drawing sheet with {$views} of the modelled piece.",
            "Model units are {$units}; dimension annotations use the same unit.",
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, string>
     */
    private function forSpecification(array $settings): array
    {
        $type = (string) ($settings['specification_type'] ?? 'manufacturing specification');
        $name = trim((string) ($settings['project_name'] ?? ''));
        $material = trim((string) ($settings['material'] ?? ''));

        $parts = [
            "Produce a {$type} sheet: a small orthographic key drawing plus a structured specification "
            .'panel listing overall dimensions, parts, materials, finishes, hardware and production notes.',
        ];

        if ($name !== '') {
            $parts[] = 'The sheet title block names the project "'.$this->plain($name).'".';
        }

        if ($material !== '') {
            $parts[] = 'The primary material is '.$this->plain($material).'.';
        }

        return $parts;
    }

    /**
     * Flatten free-form user text before it joins the prompt: these two
     * fields are the only non-closed-list values that reach the model, so
     * anything that could read as an instruction boundary — line breaks,
     * quotes — is stripped and the length capped.
     */
    private function plain(string $value): string
    {
        $flat = preg_replace('/[\r\n"\x00-\x1f\x7f]+/', ' ', $value) ?? '';

        return mb_substr(trim(preg_replace('/\s+/', ' ', $flat) ?? ''), 0, 120);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function dimensionSentence(array $settings): string
    {
        $unit = (string) ($settings['unit'] ?? 'mm');
        $width = $settings['width'] ?? null;
        $height = $settings['height'] ?? null;
        $depth = $settings['depth'] ?? null;

        if (! $width || ! $height) {
            return 'No verified overall dimensions were supplied. Leave dimensions unlabelled; do not estimate measurements from the photo.';
        }

        $size = "{$width} × {$height}".($depth ? " × {$depth}" : '');
        $axes = 'width × height'.($depth ? ' × depth' : '');

        return "The piece measures {$size} {$unit} ({$axes}); use these as the overall dimensions on the sheet.";
    }
}
