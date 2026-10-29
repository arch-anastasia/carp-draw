<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Services\Drawing\CreditLedger;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Laravel\Cashier\Billable;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property bool $has_password
 * @property string|null $social_provider
 * @property string|null $social_id
 * @property bool $is_admin
 * @property string $language
 * @property Carbon|null $credits_period_started_at
 * @property Carbon|null $email_verified_at
 * @property string|null $stripe_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasFactory, HasUuids, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'has_password',
        'social_provider',
        'social_id',
        'is_admin',
        'language',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Open the credit ledger with the free allowance.
     *
     * At creation, not at first read: the marketing copy promises free
     * credits, so they have to exist from the moment the account does —
     * granted lazily, a balance of zero would be indistinguishable from a
     * spent allowance.
     */
    protected static function booted(): void
    {
        static::created(function (User $user): void {
            app(CreditLedger::class)->grantInitialAllowance(
                $user,
                (int) config('drawing.credits.free'),
            );

            // The library never opens empty: the four default rooms exist
            // from the moment the account does.
            foreach (['Kitchen', 'Bathroom', 'Living Room', 'Bedroom'] as $room) {
                $user->projectFolders()->create(['name' => $room]);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credits_period_started_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'has_password' => 'boolean',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * The credits available to spend right now. Balance is a ledger sum —
     * see CreditLedger.
     */
    public function creditBalance(): int
    {
        return app(CreditLedger::class)->balanceFor($this);
    }

    /**
     * Generate a signed URL for password setup.
     */
    public function generateSetPasswordUrl(int $expirationMinutes = 1440): string
    {
        return URL::temporarySignedRoute(
            'password.set',
            now()->addMinutes($expirationMinutes),
            ['user' => $this->id]
        );
    }

    /**
     * Generate a signed one-time sign-in URL for accounts that already exist.
     */
    public function generateMagicLoginUrl(int $expirationMinutes = 60): string
    {
        return URL::temporarySignedRoute(
            'magic.login',
            now()->addMinutes($expirationMinutes),
            ['user' => $this->id]
        );
    }

    /**
     * Send the password reset notification.
     */
    public function sendPasswordResetNotification(mixed $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Get the customer name that should be synced to Stripe.
     */
    public function stripeName(): ?string
    {
        return $this->name;
    }

    /**
     * Determine if the user has ever subscribed to a given subscription type.
     * Used to prevent trial abuse.
     */
    public function hasEverSubscribedTo(string $type = 'default'): bool
    {
        return $this->subscriptions()->where('type', $type)->exists();
    }

    /**
     * Everything this account has created in the workspace.
     *
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * The room folders this account organizes projects into.
     *
     * @return HasMany<ProjectFolder, $this>
     */
    public function projectFolders(): HasMany
    {
        return $this->hasMany(ProjectFolder::class);
    }

    /**
     * The credit ledger. Append-only, and the only place a balance lives.
     *
     * @return HasMany<CreditTransaction, $this>
     */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }
}
