<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\Drawing\CreditLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounts_open_with_the_free_allowance(): void
    {
        $user = User::factory()->create();

        $this->assertSame(12, $user->creditBalance());
    }

    public function test_spend_and_refund_settle_back_to_the_starting_balance(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $ledger = app(CreditLedger::class);

        $ledger->spend($user, 4, $project);
        $this->assertSame(8, $user->creditBalance());

        $ledger->refund($user, $project);
        $this->assertSame(12, $user->creditBalance());

        // Idempotent: a retried refund may only return what the project is
        // on record as having taken.
        $this->assertNull($ledger->refund($user, $project));
        $this->assertSame(12, $user->creditBalance());
    }

    public function test_rollover_claws_a_surplus_back_to_the_allowance(): void
    {
        $user = User::factory()->create();
        $ledger = app(CreditLedger::class);

        $ledger->adjust($user, 500, 'test surplus');
        $ledger->rollOver($user, 40);

        $this->assertSame(40, $user->creditBalance());
    }

    public function test_upgrade_allowance_never_claws_back(): void
    {
        $user = User::factory()->create();
        $ledger = app(CreditLedger::class);

        $ledger->adjust($user, 500, 'test surplus');
        $ledger->grantUpgradeAllowance($user, 250);

        $this->assertSame(512, $user->creditBalance());
    }

    public function test_every_row_carries_its_running_balance_checksum(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $ledger = app(CreditLedger::class);

        $spend = $ledger->spend($user, 4, $project);

        $this->assertSame(8, $spend->balance_after);
        $this->assertSame(-4, $spend->amount);
    }
}
