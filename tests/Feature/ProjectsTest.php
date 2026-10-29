<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_accounts_get_the_four_default_rooms(): void
    {
        $user = User::factory()->create();

        $this->assertSame(
            ['Kitchen', 'Bathroom', 'Living Room', 'Bedroom'],
            $user->projectFolders()->orderBy('created_at')->pluck('name')->all(),
        );
    }

    public function test_projects_page_lists_folders_and_projects(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->succeeded()->create(['title' => 'Kitchen specification set']);

        $this->actingAs($user)->get('/app/projects')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('App/Projects')
                ->has('folders', 4)
                ->has('projects', 1)
                ->where('projects.0.title', 'Kitchen specification set'));
    }

    public function test_folder_creation_validates_blank_and_duplicate_names(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/app/projects')->post('/app/folders', ['name' => ''])
            ->assertSessionHasErrors(['name' => 'Enter a room or folder name.']);

        $this->actingAs($user)->from('/app/projects')->post('/app/folders', ['name' => 'kitchen'])
            ->assertSessionHasErrors(['name' => 'A folder with this name already exists.']);

        $this->actingAs($user)->from('/app/projects')->post('/app/folders', ['name' => 'Home office'])
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->projectFolders()->where('name', 'Home office')->exists());
    }

    public function test_projects_move_between_folders(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $folder = $user->projectFolders()->where('name', 'Bedroom')->first();

        $this->actingAs($user)->patch("/app/projects/{$project->id}", [
            'folder_id' => $folder->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($folder->id, $project->fresh()->project_folder_id);
    }

    public function test_projects_cannot_move_into_someone_elses_folder(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $foreignFolder = ProjectFolder::factory()->for($other)->create();

        $this->actingAs($user)->patch("/app/projects/{$project->id}", [
            'folder_id' => $foreignFolder->id,
        ])->assertSessionHasErrors('folder_id');
    }

    public function test_project_updates_are_ownership_checked(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($intruder)->patch("/app/projects/{$project->id}", ['title' => 'stolen'])
            ->assertForbidden();
    }

    public function test_workspace_home_shows_recent_projects(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->count(5)->create();

        $this->actingAs($user)->get('/app')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('App/Home')
                ->has('recentProjects', 4));
    }
}
