<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_opens_workspace_without_email(): void
    {
        Notification::fake();
        $this->get('/signup')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Auth/SignIn')->where('registering', true));
        $this->post('/signup', [
            'email' => ' New@Example.com ',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ])->assertRedirect(route('workspace'));
        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->has_password);
        $this->assertTrue(Hash::check('secure-password', $user->password));
        $this->assertNull($user->email_verified_at);
        $this->assertCount(4, $user->projectFolders);
        $this->assertSame((int) config('drawing.credits.free'), $user->creditBalance());
        Notification::assertNothingSent();
        $this->get('/app')->assertOk();
        $this->post('/signout');
        $this->post('/signin', ['email' => ' NEW@EXAMPLE.COM ', 'password' => 'secure-password'])
            ->assertRedirect(route('workspace'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_registration_does_not_create_an_account(): void
    {
        foreach ([
            ['email' => 'not-email', 'password' => 'secure-password', 'password_confirmation' => 'secure-password'],
            ['email' => 'new@example.com', 'password' => 'short', 'password_confirmation' => 'short'],
            ['email' => 'new@example.com', 'password' => 'secure-password', 'password_confirmation' => 'different'],
        ] as $data) {
            $this->post('/signup', $data)->assertSessionHasErrors();
            $this->assertGuest();
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_accounts_cannot_be_overwritten_including_passwordless_accounts(): void
    {
        foreach ([true, false] as $hasPassword) {
            $user = User::factory()->create(['has_password' => $hasPassword]);
            $password = $user->password;
            $this->post('/signup', [
                'email' => $user->email,
                'password' => 'attacker-password',
                'password_confirmation' => 'attacker-password',
            ])->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->assertSame($password, $user->fresh()->password);
        }
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/signup', [])->assertUnprocessable();
        }
        $this->postJson('/signup', [])->assertTooManyRequests();
    }
}
