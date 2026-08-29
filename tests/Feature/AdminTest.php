<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_area_is_forbidden_to_regular_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
    }

    public function test_admins_see_the_user_list_and_stats(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(3)->create();
        $this->createSubscribedUser();

        $this->actingAs($admin)->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users')
                ->where('stats.total', 5)
                ->where('stats.subscribers', 1)
                ->where('stats.weekly', 5)
                ->has('users.data', 5));
    }
}
