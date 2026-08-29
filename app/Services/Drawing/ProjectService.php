<?php

namespace App\Services\Drawing;

use App\Enums\ProjectStatus;
use App\Enums\WorkflowMode;
use App\Jobs\GenerateDrawing;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The workflow pipeline: entitlements, credits, upload storage, and the
 * queued generation behind every project.
 */
class ProjectService
{
    public function __construct(
        private CreditLedger $ledger,
        private ProviderRegistry $registry,
    ) {}

    /**
     * Whether real customer generations are allowed here — a stand-in
     * backend must never serve production.
     */
    public function isLive(): bool
    {
        return $this->registry->isLive();
    }

    /**
     * Credits left in the current window. Reading the balance settles what
     * is due first: the opening grant for a ledgerless account, and the
     * monthly rollover for a subscriber.
     */
    public function availableCredits(User $user): int
    {
        $this->openingGrantIfDue($user);
        $this->rolloverIfDue($user);

        return max(0, $this->ledger->balanceFor($user));
    }

    /**
     * Whether this account's plan includes 3D model processing.
     */
    public function canModelDrawing(User $user): bool
    {
        return (bool) ($this->entitlementsFor($user)['model_drawing'] ?? false);
    }

    /**
     * Validate, charge and queue one workflow run.
     *
     * Everything money touches happens inside one transaction that locks the
     * account row first, so two concurrent submissions cannot both read the
     * same balance and both pass the check. On any throw the stored upload
     * is deleted — a crash must not leave an orphaned file on disk.
     *
     * @param  array<string, mixed>  $settings
     *
     * @throws DrawingFailed
     */
    public function create(User $user, WorkflowMode $mode, UploadedFile $file, array $settings = []): Project
    {
        /*
         * A stand-in backend must never serve production: a canned sample
         * sheet billed as the customer's own drawing is a lie. Refused
         * BEFORE credits move.
         */
        if (app()->environment('production') && ! $this->isLive()) {
            throw DrawingFailed::permanent(
                'not_live',
                'Drawing generation is not available right now. Your credits are untouched.',
            );
        }

        if ($mode === WorkflowMode::ModelDrawing && ! $this->canModelDrawing($user)) {
            throw DrawingFailed::permanent('plan_required', '3D model processing is available on the Studio plan.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (! $this->registry->supportsSource($extension)) {
            throw DrawingFailed::permanent(
                'unsupported_source',
                'This source format is not supported for processing yet. Try a photo instead.',
            );
        }

        $cost = $mode->cost();

        // Settle grants/rollover before the locked check reads the balance.
        $this->availableCredits($user);

        $projectId = (string) Str::uuid7();
        $sourcePath = "drawings/{$user->id}/{$projectId}/source.{$extension}";
        $disk = Storage::disk((string) config('drawing.provider.disk'));

        try {
            return DB::transaction(function () use ($user, $mode, $file, $settings, $cost, $projectId, $sourcePath, $disk) {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                if ($this->ledger->balanceFor($user) < $cost) {
                    throw DrawingFailed::permanent('insufficient_credits', 'Not enough credits for this creation.');
                }

                $disk->put($sourcePath, (string) file_get_contents($file->getRealPath()));

                $project = Project::query()->create([
                    'id' => $projectId,
                    'user_id' => $user->id,
                    'title' => $this->titleFor($mode, $file, $settings),
                    'workflow' => $mode,
                    'status' => ProjectStatus::Queued,
                    'source_path' => $sourcePath,
                    'source_filename' => (string) $file->getClientOriginalName(),
                    'settings' => $settings,
                    'credits_spent' => $cost,
                ]);

                $this->ledger->spend($user, $cost, $project, $mode->typeLabel().' queued');

                GenerateDrawing::dispatch($project->id)->afterCommit();

                return $project;
            });
        } catch (\Throwable $e) {
            try {
                $disk->delete($sourcePath);
            } catch (\Throwable) {
                // Already the state we want.
            }

            throw $e;
        }
    }

    /**
     * The polling payload for one project — the contract the wizard's result
     * stage reads.
     *
     * @return array<string, mixed>
     */
    public function statusPayload(Project $project): array
    {
        return [
            'id' => $project->id,
            'status' => $project->status->value,
            'output_url' => $project->status === ProjectStatus::Succeeded ? $project->outputUrl() : null,
            'error' => $project->status === ProjectStatus::Failed ? $project->error_message : null,
            'credits_available' => $this->availableCredits($project->user),
        ];
    }

    /**
     * The allowance the account's plan promises per month.
     */
    public function allowanceFor(User $user): int
    {
        $entitlements = $this->entitlementsFor($user);

        if ($entitlements === null) {
            return (int) config('drawing.credits.free');
        }

        return (int) ($entitlements['credits'] ?? config('drawing.credits.subscribed_allowance'));
    }

    /**
     * The entitlements of the account's best active plan; null for a free
     * account. An active subscription with no plan entry (a plan removed
     * from config) keeps subscriber defaults rather than losing access it
     * already paid for.
     *
     * @return array{credits?: int, model_drawing?: bool, formats?: array<int, string>}|null
     */
    private function entitlementsFor(User $user): ?array
    {
        $activeTypes = Subscription::query()
            ->where('user_id', $user->id)
            ->get()
            ->filter(fn (Subscription $subscription) => $subscription->active())
            ->pluck('type');

        if ($activeTypes->isEmpty()) {
            return null;
        }

        $best = null;

        foreach (config('stripe.plans', []) as $plan) {
            if (! $activeTypes->contains($plan['subscription_name'] ?? null)) {
                continue;
            }

            $entitlements = $plan['entitlements'] ?? null;

            if ($entitlements !== null && ($best === null || ($entitlements['credits'] ?? 0) > ($best['credits'] ?? 0))) {
                $best = $entitlements;
            }
        }

        return $best ?? [
            'credits' => (int) config('drawing.credits.subscribed_allowance'),
            'model_drawing' => true,
            'formats' => ['pdf', 'svg', 'dxf'],
        ];
    }

    /**
     * An account that has never had a ledger entry gets its free allowance
     * the first time the balance is asked for.
     */
    private function openingGrantIfDue(User $user): void
    {
        if ($this->ledger->hasLedger($user)) {
            return;
        }

        $this->ledger->grantInitialAllowance($user, (int) config('drawing.credits.free'));
    }

    /**
     * Subscribers get a fresh allowance every month. The free allowance does
     * not roll over.
     */
    private function rolloverIfDue(User $user): void
    {
        if ($this->entitlementsFor($user) === null) {
            return;
        }

        $startedAt = $user->credits_period_started_at;

        /*
         * First window of a subscription (the Checkout path grants nothing
         * directly): top UP to the allowance without clawing back. A
         * returning subscriber may hold more credits than the new plan's
         * allowance — credits they already paid for — and an opening
         * "rollover" that clawed them away would charge them for
         * subscribing.
         */
        if ($startedAt === null) {
            $this->ledger->grantUpgradeAllowance($user, $this->allowanceFor($user));

            return;
        }

        if ($startedAt->copy()->addMonth()->isFuture()) {
            return;
        }

        /*
         * Anchor the new window to the BILLING rhythm, not the read time:
         * stamping "now" on every rollover let each late read push the next
         * refresh later, so a subscriber reading on day 35 every cycle got
         * ~10 refreshes for 12 payments. The window advances by whole
         * months from the original start.
         */
        $anchor = $startedAt->copy();

        while ($anchor->copy()->addMonth()->isPast()) {
            $anchor = $anchor->addMonth();
        }

        $this->ledger->rollOver($user, $this->allowanceFor($user), $anchor);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function titleFor(WorkflowMode $mode, UploadedFile $file, array $settings): string
    {
        $name = trim((string) ($settings['project_name'] ?? ''));

        if ($name !== '') {
            return Str::limit($name, 120, '');
        }

        $stem = trim((string) pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME));

        if ($stem !== '') {
            return Str::limit(Str::of($stem)->replace(['-', '_'], ' ')->squish()->ucfirst()->toString(), 120, '');
        }

        return 'Untitled '.strtolower($mode->typeLabel());
    }
}
