<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_shows_real_credits_and_language(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/app/settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('App/Settings')
                ->where('credits', 12)
                ->where('language', 'en'));
    }

    public function test_profile_updates_persist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/app/settings/profile', [
            'name' => 'Anastasia Ivanova',
            'language' => 'ru',
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Anastasia Ivanova', $user->name);
        $this->assertSame('ru', $user->language);
    }

    public function test_blank_names_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/app/settings/profile', ['name' => ''])
            ->assertSessionHasErrors(['name' => 'Name is required.']);
    }
}
