<?php

namespace App\Models;

use App\Enums\CreditTransactionType;
use Database\Factories\CreditTransactionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One movement in the credit ledger.
 *
 * Append-only. Nothing in the application updates or deletes a row here: a
 * correction is another row, which is what makes the history worth keeping.
 * The balance is SUM(amount), so this table is not a log beside the truth —
 * it IS the truth.
 *
 * @property string $id
 * @property string $user_id
 * @property CreditTransactionType $type
 * @property int $amount
 * @property int $balance_after
 * @property string|null $project_id
 * @property string|null $description
 */
class CreditTransaction extends Model
{
    /** @use HasFactory<CreditTransactionFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'balance_after',
        'project_id',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CreditTransactionType::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Set when the movement was caused by a workflow run.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
