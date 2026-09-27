<?php

namespace Tests\Feature\Blog\Admin;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_categories_are_admin_only(): void
    {
        $this->get('/admin/categories')->assertRedirect('/signin');
        $this->actingAs(User::factory()->create())->get('/admin/categories')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/categories')->assertOk();
    }

    public function test_the_editor_screens_render(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin())
            ->get('/admin/categories/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Blog/Categories/Create'));

        $this->actingAs($this->admin())
            ->get('/admin/categories/'.$category->id.'/edit')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Blog/Categories/Edit')
                ->where('category.id', $category->id));

        foreach (['Index', 'Create', 'Edit'] as $page) {
            $this->assertFileExists(resource_path("js/Pages/Admin/Blog/Categories/{$page}.vue"));
        }
    }

    public function test_a_category_can_be_created_and_its_hub_goes_live(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/categories', [
                'name' => 'Barber Briefs',
                'description' => 'How to ask for it.',
                'audience' => 'men',
            ])
            ->assertRedirect('/admin/categories');

        $category = Category::query()->where('name', 'Barber Briefs')->firstOrFail();

        $this->assertSame('barber-briefs', $category->slug);
        $this->assertSame('men', $category->audience);
        $this->get('/blog/barber-briefs')->assertOk();
    }

    public function test_a_slug_already_used_by_a_post_is_refused(): void
    {
        $post = Post::factory()->create(['slug' => 'taken-by-a-guide']);

        $this->actingAs($this->admin())
            ->post('/admin/categories', ['name' => 'Anything', 'slug' => 'taken-by-a-guide'])
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, Post::query()->where('slug', $post->slug)->count());
    }

    public function test_deleting_a_category_unfiles_its_posts_but_keeps_them(): void
    {
        $category = Category::factory()->create();
        $post = Post::factory()->create();
        $post->categories()->attach($category);

        $this->actingAs($this->admin())
            ->delete('/admin/categories/'.$category->id)
            ->assertRedirect('/admin/categories');

        $this->assertNull($category->fresh());
        $this->assertNotNull($post->fresh());
        $this->get('/blog/'.$post->slug)->assertOk();
    }

    public function test_a_name_is_required(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/categories', ['name' => ''])
            ->assertSessionHasErrors('name');
    }
}
