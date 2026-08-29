<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\MagicLinkNotification;
use App\Notifications\SetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SignInFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_signin_page_renders_with_no_social_providers_when_unconfigured(): void
    {
        $this->get('/signin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/SignIn')
                ->where('socialProviders', []));
    }

    public function test_check_email_reports_existence_and_password_state(): void
    {
        $user = User::factory()->create(['email' => 'maker@example.com']);

        $this->postJson('/signin/check-email', ['email' => 'maker@example.com'])
            ->assertOk()
            ->assertJson(['exists' => true, 'hasPassword' => true]);

        $this->postJson('/signin/check-email', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJson(['exists' => false, 'hasPassword' => false]);
    }

    public function test_email_entry_creates_a_passwordless_account_and_sends_set_password_link(): void
    {
        Notification::fake();

        $this->postJson('/signin/email', ['email' => 'new@example.com'])
            ->assertOk()
            ->assertJson(['status' => 'sent']);

        $user = User::where('email', 'new@example.com')->first();

        $this->assertNotNull($user);
        $this->assertFalse($user->has_password);
        Notification::assertSentTo($user, SetPasswordNotification::class);

        // The email path never logs anyone in directly.
        $this->assertGuest();
    }

    public function test_email_entry_sends_magic_link_to_an_account_with_a_password(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'maker@example.com']);

        $this->postJson('/signin/email', ['email' => 'maker@example.com'])
            ->assertOk();

        Notification::assertSentTo($user, MagicLinkNotification::class);
    }

    public function test_magic_link_signs_the_user_in(): void
    {
        $user = User::factory()->create();

        $this->get($user->generateMagicLoginUrl())
            ->assertRedirect(route('workspace'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_magic_links_are_single_use(): void
    {
        $user = User::factory()->create();
        $url = $user->generateMagicLoginUrl();

        $this->get($url)->assertRedirect(route('workspace'));

        // A replay — leaked or forwarded — must not sign anyone in again.
        $this->post('/signout');
        $this->get($url)
            ->assertRedirect(route('signin'))
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_set_password_links_stop_authenticating_once_a_password_exists(): void
    {
        $user = User::factory()->passwordless()->create();
        $url = $user->generateSetPasswordUrl();

        // The owner sets a password through the link…
        $this->get($url);
        $this->post(url()->signedRoute('password.set.store', ['user' => $user->id]), [
            'password' => 'a-new-password',
            'password_confirmation' => 'a-new-password',
        ]);
        $this->post('/signout');

        // …after which the same link must not be a sign-in for anyone.
        $this->get($url)->assertRedirect(route('signin'));
        $this->assertGuest();
    }

    public function test_login_with_password_reaches_the_workspace(): void
    {
        $user = User::factory()->create(['email' => 'maker@example.com']);

        $this->post('/signin', [
            'email' => 'maker@example.com',
            'password' => 'password',
        ])->assertRedirect(route('workspace'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create(['email' => 'maker@example.com']);

        $this->from('/signin')->post('/signin', [
            'email' => 'maker@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_set_password_link_signs_in_and_sets_password(): void
    {
        $user = User::factory()->passwordless()->create();

        $this->get($user->generateSetPasswordUrl())->assertOk();
        $this->assertAuthenticatedAs($user);

        $this->post(url()->signedRoute('password.set.store', ['user' => $user->id]), [
            'password' => 'a-new-password',
            'password_confirmation' => 'a-new-password',
        ])->assertRedirect(route('workspace'));

        $this->assertTrue($user->fresh()->has_password);
    }

    public function test_guests_are_redirected_to_signin_from_the_app(): void
    {
        $this->get('/app')->assertRedirect('/signin');
    }

    public function test_signout_returns_to_the_marketing_home(): void
    {
        $user = User::factory()->create();

        // A plain (non-Inertia) request gets a standard redirect home.
        $this->actingAs($user)->post('/signout')->assertRedirect('/');

        $this->assertGuest();
    }
}
