<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Statuses\StatusResource;
use App\Models\Category;
use App\Models\Project;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryStatusAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'secret-password',
        ]);
    }

    public function test_category_and_status_resource_pages_render(): void
    {
        $admin = $this->admin();

        foreach ([
            CategoryResource::getUrl('index'),
            CategoryResource::getUrl('create'),
            StatusResource::getUrl('index'),
            StatusResource::getUrl('create'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_project_editor_options_come_from_the_database(): void
    {
        $admin = $this->admin();

        // A category added in the admin should appear as a project option.
        Category::create(['key' => 'educational', 'name' => 'Educational', 'sort_order' => 99]);
        Status::create(['name' => 'On Hold', 'badge' => 'On Hold', 'tone' => 'build', 'sort_order' => 99]);

        $this->assertArrayHasKey('educational', Category::options());
        $this->assertContains('On Hold', Status::options());

        $project = Project::create([
            'name' => 'Test', 'category' => 'educational', 'status' => 'On Hold', 'size' => 'default',
        ]);

        $this->actingAs($admin)->get(ProjectResource::getUrl('edit', ['record' => $project]))
            ->assertOk()
            ->assertSee('Educational', false)
            ->assertSee('On Hold', false);

        // Model helpers resolve the new types.
        $this->assertSame('Educational', $project->categoryLabel());
        $this->assertSame('build', $project->statusClass());
        $this->assertSame('On Hold', $project->statusBadge());

        // Deleting removes them from the options (add/delete round-trip).
        // Delete the model instance (as Filament does) so cache-clearing events fire.
        Category::where('key', 'educational')->first()->delete();
        Status::where('name', 'On Hold')->first()->delete();
        $this->assertArrayNotHasKey('educational', Category::options());
        $this->assertArrayNotHasKey('On Hold', Status::options());
    }

    public function test_seeded_defaults_exist(): void
    {
        $this->assertSame('Cultural', Category::map()['cultural'] ?? null);
        $this->assertSame('concept', Status::meta()['Concept / Planning']['tone'] ?? null);
    }
}
