<?php

namespace Tests\Feature\Blog\Admin;

use App\Models\Author;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** The writers' desk is the only door through which a byline is created. */
class AuthorControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_the_desk_is_closed_to_everyone_but_admins(): void
    {
        $this->get('/admin/authors')->assertRedirect('/signin');
        $this->actingAs(User::factory()->create())->get('/admin/authors')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/authors')->assertOk();
    }

    public function test_the_desk_and_editor_screens_render(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/authors')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Blog/Authors/Index')->has('authors'));
        $this->actingAs($admin)->get('/admin/authors/create')->assertOk();

        $author = Author::factory()->create();
        $this->actingAs($admin)->get('/admin/authors/'.$author->id.'/edit')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('author.id', $author->id));
    }

    public function test_create_and_edit_round_trip_every_field_including_an_avatar_upload(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $account = User::factory()->create();

        $this->actingAs($admin)->post('/admin/authors', [
            'name' => 'Dana Reeve',
            'username' => 'dana-reeve',
            'role' => 'Barber',
            'bio' => 'Cuts hair and writes about it.',
            'avatar' => UploadedFile::fake()->image('dana.png', 80, 80),
            'website_url' => 'https://dana.example',
            'links' => [['label' => 'Instagram', 'url' => 'https://instagram.com/dana']],
            'profile_is_published' => true,
            'user_id' => $account->id,
        ])->assertRedirect('/admin/authors');

        $author = Author::query()->where('username', 'dana-reeve')->firstOrFail();
        $this->assertSame($account->id, $author->user_id);
        $this->assertNotNull($author->avatar_media_id);
        $this->assertTrue($author->profile_is_published);
        $this->assertSame([['label' => 'Instagram', 'url' => 'https://instagram.com/dana']], $author->links);
        Storage::disk('public')->assertExists((string) $author->avatar?->path);

        $this->actingAs($admin)->get('/admin/authors/'.$author->id.'/edit')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('author.name', 'Dana Reeve')
            ->where('author.username', 'dana-reeve')
            ->where('author.role', 'Barber')
            ->where('author.bio', 'Cuts hair and writes about it.')
            ->where('author.website_url', 'https://dana.example')
            ->where('author.user_id', $account->id)
            ->has('author.links', 1));

        $this->actingAs($admin)->put('/admin/authors/'.$author->id, [
            'name' => 'Dana Reeve Updated',
            'username' => 'dana-reeve',
            'role' => 'Editor',
            'bio' => 'Updated biography.',
            'website_url' => 'https://updated.example',
            'links' => [['label' => 'Work', 'url' => 'https://work.example']],
            'profile_is_published' => false,
            'user_id' => null,
        ])->assertRedirect('/admin/authors');

        $author->refresh();
        $this->assertSame('Dana Reeve Updated', $author->name);
        $this->assertSame('Editor', $author->role);
        $this->assertFalse($author->profile_is_published);
        $this->assertNull($author->user_id);
    }

    public function test_username_and_name_validation_are_useful(): void
    {
        Author::factory()->create(['username' => 'taken']);

        $this->actingAs($this->admin())->post('/admin/authors', ['name' => '', 'username' => 'Not Safe!'])
            ->assertSessionHasErrors(['name', 'username']);
        $this->actingAs($this->admin())->post('/admin/authors', ['name' => 'Other', 'username' => 'taken'])
            ->assertSessionHasErrors('username');
    }

    public function test_deleting_a_linked_user_leaves_the_author_standing(): void
    {
        $user = User::factory()->create();
        $author = Author::factory()->create(['user_id' => $user->id]);

        $user->forceDelete();

        $this->assertDatabaseHas('authors', ['id' => $author->id, 'user_id' => null]);
    }

    public function test_only_an_unused_author_can_be_deleted(): void
    {
        $admin = $this->admin();
        $unused = Author::factory()->create();
        $used = Author::factory()->create();
        Post::factory()->by($used)->create();

        $this->actingAs($admin)->delete('/admin/authors/'.$unused->id)->assertRedirect('/admin/authors');
        $this->assertSoftDeleted($unused);

        $this->actingAs($admin)->delete('/admin/authors/'.$used->id)->assertSessionHasErrors('author');
        $this->assertNotSoftDeleted($used);
    }
}
