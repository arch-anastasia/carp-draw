<?php

namespace App\Enums;

/**
 * The three creation workflows.
 *
 * Backed values are the snake_case identifiers stored on `projects.workflow`;
 * the client speaks camelCase (`modelDrawing`) — `fromClient()`/`clientValue()`
 * translate at the boundary so neither side has to change its dialect.
 */
enum WorkflowMode: string
{
    case Drawing = 'drawing';
    case ModelDrawing = 'model_drawing';
    case Specification = 'specification';

    public static function fromClient(string $value): ?self
    {
        return match ($value) {
            'drawing' => self::Drawing,
            'modelDrawing' => self::ModelDrawing,
            'specification' => self::Specification,
            default => null,
        };
    }

    public function clientValue(): string
    {
        return match ($this) {
            self::Drawing => 'drawing',
            self::ModelDrawing => 'modelDrawing',
            self::Specification => 'specification',
        };
    }

    /**
     * The label shown on project cards ("{type} · {updated}").
     */
    public function typeLabel(): string
    {
        return match ($this) {
            self::Drawing => 'Photo to drawing',
            self::ModelDrawing => '3D model drawing',
            self::Specification => 'Specification',
        };
    }

    /**
     * The workspace route that opens this workflow.
     */
    public function routeName(): string
    {
        return match ($this) {
            self::Drawing => 'workflow.drawing',
            self::ModelDrawing => 'workflow.model',
            self::Specification => 'workflow.specification',
        };
    }

    /**
     * Credits one run of this workflow costs.
     */
    public function cost(): int
    {
        return (int) config('drawing.credits.actions.'.$this->value, 1);
    }

    /**
     * @return array{extensions: array<int, string>, max_mb: int}
     */
    public function uploadRules(): array
    {
        /** @var array{extensions: array<int, string>, max_mb: int} */
        return config('drawing.uploads.'.$this->value);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
