<?php

namespace Tests\Feature;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapPickerRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_edit_page_renders_map_picker(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'secret-password',
        ]);

        $project = Project::create([
            'name' => 'Test Project',
            'category' => 'cultural',
            'status' => 'Completed',
            'size' => 'default',
            'lat' => 36.32,
            'lng' => 43.73,
        ]);

        $url = ProjectResource::getUrl('edit', ['record' => $project]);

        $res = $this->actingAs($admin)->get($url);

        $res->assertOk();
        // the map picker field + its wire-ignored container are present
        $res->assertSee('amp-map', false);
        $res->assertSee('mapPicker(', false);
        // sibling lat/lng are wired for the picker to read/write
        $res->assertSee('data.lat', false);
        $res->assertSee('data.lng', false);
    }

    public function test_project_create_page_renders_map_picker(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin2@example.test',
            'password' => 'secret-password',
        ]);

        $res = $this->actingAs($admin)->get(ProjectResource::getUrl('create'));

        $res->assertOk();
        $res->assertSee('amp-map', false);
        $res->assertSee('mapPicker(', false);
    }
}
