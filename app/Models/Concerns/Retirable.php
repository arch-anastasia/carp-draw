<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

trait Retirable
{
    /**
     * Rows still offered to visitors.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereNull('retired_at');
    }

    /**
     * Rows withdrawn from the product but still resolvable.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRetired(Builder $query): Builder
    {
        return $query->whereNotNull('retired_at');
    }

    public function isRetired(): bool
    {
        return $this->retired_at !== null;
    }

    public function isLive(): bool
    {
        return $this->retired_at === null;
    }

    public function retire(?Carbon $at = null): bool
    {
        if ($this->isRetired()) {
            return false;
        }

        $this->retired_at = $at ?? Carbon::now();

        return $this->save();
    }

    public function reinstate(): bool
    {
        if ($this->isLive()) {
            return false;
        }

        $this->retired_at = null;

        return $this->save();
    }
}
