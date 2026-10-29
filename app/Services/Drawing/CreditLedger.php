<?php

namespace App\Services\Drawing;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Credits, as an append-only ledger.
 *
 * The balance is SUM(amount) and nothing else. There is no counter to keep in
 * step with it and no period to filter by: rolling an account over inserts a
 * grant for exactly the shortfall, so the all-time sum lands back on the
 * allowance.
 *
 * Every write goes through `record()`, inside a transaction that locks the
 * account row first, so two concurrent submissions cannot both read the same
 * balance and both pass the check.
 */
class CreditLedger
{
    /**
     * The credits available to spend right now.
     */
    public function balanceFor(User $user): int
    {
        return (int) CreditTransaction::query()
            ->where('user_id', $user->id)
            ->sum('amount');
    }

    /**
     * Charge for a workflow run. Returns the transaction so a caller can
     * prove what it took.
     */
    public function spend(User $user, int $amount, Project $project, ?string $description = null): CreditTransaction
    {
        return $this->record(
            $user,
            CreditTransactionType::Spend,
            -abs($amount),
            $project,
            $description ?? 'Workflow queued',
        );
    }

    /**
     * Hand a project's credits back.
     *
     * The amount comes from the LEDGER, not from `credits_spent` on the
     * project: a refund may only return what this project is on record as
     * having taken, minus anything already given back. That makes it
     * idempotent — a job that retries after a partial failure cannot refund
     * twice — and it makes it impossible to invent credit.
     *
     * The refundable sum is computed INSIDE the locked transaction: read
     * outside the lock, two concurrent failure paths (the job's fail and
     * its failed() hook, or a delete racing a failure) could both see the
     * full amount and both pay it.
     */
    public function refund(User $user, Project $project, ?string $reason = null): ?CreditTransaction
    {
        return DB::transaction(function () use ($user, $project, $reason) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $movements = CreditTransaction::query()
                ->where('user_id', $locked->id)
                ->where('project_id', $project->id)
                ->whereIn('type', [CreditTransactionType::Spend, CreditTransactionType::Refund])
                ->sum('amount');

            $refundable = (int) -$movements;

            if ($refundable <= 0) {
                return null;
            }

            return $this->write(
                $locked,
                CreditTransactionType::Refund,
                $refundable,
                $project,
                $reason ?? 'Generation failed',
            );
        });
    }

    /**
     * Give an account its opening allowance.
     *
     * Called once, when the account is created. Nothing renews it — the free
     * allowance is not a monthly entitlement.
     */
    public function grantInitialAllowance(User $user, int $amount): ?CreditTransaction
    {
        return DB::transaction(function () use ($user, $amount) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($amount <= 0 || $this->hasLedger($locked)) {
                return null;
            }

            return $this->write($locked, CreditTransactionType::Grant, $amount, null, 'Free allowance');
        });
    }

    /**
     * Top an account back up to its allowance and open a new window.
     *
     * The grant is the SHORTFALL, not the allowance, so the running sum lands
     * exactly on it however much was left over. At a rollover a balance ABOVE
     * the allowance is clawed back down to it.
     *
     * `$periodStart` anchors the new window (the caller advances it in whole
     * months from the original start so late reads don't drift the billing
     * rhythm); null stamps now.
     */
    public function rollOver(User $user, int $allowance, ?Carbon $periodStart = null): ?CreditTransaction
    {
        return $this->settlePeriodTo($user, $allowance, 'Monthly allowance', clawBack: true, periodStart: $periodStart);
    }

    /**
     * Start a new credit window at the new plan's full allowance, the moment
     * a visitor upgrades. Unlike a rollover this never claws back: an upgrade
     * should never cost a visitor credits they already hold.
     */
    public function grantUpgradeAllowance(User $user, int $allowance): ?CreditTransaction
    {
        return $this->settlePeriodTo($user, $allowance, 'Upgrade allowance', clawBack: false);
    }

    /**
     * An operator's correction. Signed: negative takes credits away.
     */
    public function adjust(User $user, int $amount, string $reason): CreditTransaction
    {
        return $this->record($user, CreditTransactionType::Adjustment, $amount, null, $reason);
    }

    /**
     * Whether this account has ever had a ledger entry.
     */
    public function hasLedger(User $user): bool
    {
        return CreditTransaction::query()->where('user_id', $user->id)->exists();
    }

    /**
     * Move the balance to `$allowance` and restart the credit window.
     */
    private function settlePeriodTo(
        User $user,
        int $allowance,
        string $description,
        bool $clawBack,
        ?Carbon $periodStart = null,
    ): ?CreditTransaction {
        return DB::transaction(function () use ($user, $allowance, $description, $clawBack, $periodStart) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $delta = $allowance - $this->balanceFor($locked);

            if (! $clawBack && $delta < 0) {
                $delta = 0;
            }

            $transaction = $delta === 0
                ? null
                : $this->write($locked, CreditTransactionType::Grant, $delta, null, $description);

            $locked->forceFill(['credits_period_started_at' => $periodStart ?? now()])->save();

            $user->setRawAttributes($locked->getAttributes(), true);

            return $transaction;
        });
    }

    /**
     * Write one movement inside a locked transaction.
     */
    private function record(
        User $user,
        CreditTransactionType $type,
        int $amount,
        ?Project $project,
        ?string $description,
    ): CreditTransaction {
        return DB::transaction(function () use ($user, $type, $amount, $project, $description) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            return $this->write($locked, $type, $amount, $project, $description);
        });
    }

    /**
     * The insert itself. Assumes the account row is already locked.
     */
    private function write(
        User $user,
        CreditTransactionType $type,
        int $amount,
        ?Project $project,
        ?string $description,
    ): CreditTransaction {
        return CreditTransaction::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'amount' => $amount,
            /*
             * Redundant by construction, and that is the point: it is the
             * ledger's own checksum, so a disagreement with the running sum
             * is a bug that can be found rather than one that quietly
             * shortchanges somebody.
             */
            'balance_after' => $this->balanceFor($user) + $amount,
            'project_id' => $project?->id,
            'description' => $description,
        ]);
    }
}
