<?php

namespace App\Enums;

/**
 * Where a project's generation stands.
 */
enum ProjectStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function isFinished(): bool
    {
        return match ($this) {
            self::Succeeded, self::Failed => true,
            self::Queued, self::Processing => false,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
