<?php

namespace App\Enums;

/**
 * Why credits moved.
 *
 * The ledger is append-only, so every balance the product ever showed can be
 * reconstructed and explained. A support question about a missing credit has
 * a row that answers it.
 */
enum CreditTransactionType: string
{
    /** The allowance a plan or the free tier grants at the start of a window. */
    case Grant = 'grant';

    /** Charged when a batch is queued. */
    case Spend = 'spend';

    /** Handed back when a batch could not be delivered. */
    case Refund = 'refund';

    /** A manual correction made by an operator. */
    case Adjustment = 'adjustment';

    /**
     * Whether this type adds to the balance rather than taking from it.
     */
    public function isCredit(): bool
    {
        return match ($this) {
            self::Grant, self::Refund => true,
            self::Spend => false,
            self::Adjustment => true,
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
