<?php

namespace Tests\Feature;

use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectPlaceCoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_string_is_composed_from_the_structured_parts(): void
    {
        $p = Project::create([
            'name' => 'X', 'category' => 'cultural', 'status' => 'Completed', 'size' => 'default',
            'neighbourhood' => 'Kawrgusk', 'city' => 'Erbil', 'country' => 'Kurdistan Region',
        ]);

        $this->assertSame('Kawrgusk, Erbil, Kurdistan Region', $p->location);
        $this->assertSame('Erbil', $p->cityLabel());
    }

    public function test_cover_choice_and_focal_point_drive_the_grid_cover(): void
    {
        $imgs = ['https://ex.com/a.jpg', 'https://ex.com/b.jpg', 'https://ex.com/c.jpg'];
        $p = Project::create([
            'name' => 'X', 'category' => 'cultural', 'status' => 'Completed', 'size' => 'default',
            'city' => 'Erbil', 'country' => 'Iraq', 'imgs' => $imgs,
            'cover' => 'https://ex.com/b.jpg', 'cover_x' => 30, 'cover_y' => 70,
        ]);

        $this->assertSame('https://ex.com/b.jpg', $p->coverImage());
        $this->assertSame('https://ex.com/b.jpg', $p->coverUrl());
        $this->assertSame('30% 70%', $p->coverPosition());
        // the overlay/grid see the cover first
        $this->assertSame('https://ex.com/b.jpg', $p->orderedImageUrls()[0]);

        // an invalid/removed cover falls back to the first image
        $p->update(['cover' => 'https://ex.com/gone.jpg']);
        $this->assertSame('https://ex.com/a.jpg', $p->coverImage());
    }

    public function test_a_new_project_saves_without_a_focal_point(): void
    {
        // The cover picker only appears once a project has images, so creating
        // one sends cover_x / cover_y as null — they must fall back to centre
        // instead of hitting the columns' NOT NULL constraint.
        $p = Project::create([
            'name' => 'MZ07', 'category' => 'residential', 'status' => 'Completed', 'size' => 'default',
            'cover' => null, 'cover_x' => null, 'cover_y' => null,
        ]);

        $this->assertSame(50, $p->fresh()->cover_x);
        $this->assertSame(50, $p->fresh()->cover_y);
        $this->assertSame('50% 50%', $p->coverPosition());
    }

    public function test_the_create_form_submits_with_the_cover_picker_untouched(): void
    {
        $admin = User::create(['name' => 'A', 'email' => 'create@b.test', 'password' => 'secret-password']);

        $this->actingAs($admin);
        Livewire::test(CreateProject::class)
            ->fillForm([
                'name' => 'MZ07',
                'categories' => ['residential'],
                'status' => 'Completed',
                'size' => 'default',
                'city' => 'Erbil',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(50, Project::where('name', 'MZ07')->sole()->cover_x);
    }

    public function test_project_edit_page_renders_location_fields_and_cover_picker(): void
    {
        $admin = User::create(['name' => 'A', 'email' => 'a@b.test', 'password' => 'secret-password']);
        $p = Project::create([
            'name' => 'X', 'category' => 'cultural', 'status' => 'Completed', 'size' => 'default',
            'city' => 'Erbil', 'country' => 'Iraq', 'imgs' => ['https://ex.com/a.jpg'],
        ]);

        $this->actingAs($admin)->get(ProjectResource::getUrl('edit', ['record' => $p]))
            ->assertOk()
            ->assertSee('Neighbourhood', false)
            ->assertSee('coverPicker(', false);
    }
}
