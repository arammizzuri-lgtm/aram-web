<?php

namespace Tests\Feature;

use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Models\Category;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A project can sit in several categories at once (Residential *and* Exterior).
 * The list is authoritative; the single `category` column stays in sync with its
 * first entry so everything that shows one category keeps working.
 */
class ProjectCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'A', 'email' => 'cat@b.test', 'password' => 'secret-password']);
    }

    private function project(array $attrs = []): Project
    {
        return Project::create($attrs + [
            'name' => 'Villa', 'status' => 'Completed', 'size' => 'default', 'city' => 'Erbil',
        ]);
    }

    public function test_a_project_keeps_several_categories_and_a_primary_one(): void
    {
        Category::create(['key' => 'exterior', 'name' => 'Exterior', 'sort_order' => 90]);

        $p = $this->project(['categories' => ['residential', 'exterior'], 'year' => '2022 – 2024']);

        $this->assertSame(['residential', 'exterior'], $p->fresh()->categories);
        // the first pick is the primary, kept in the original column
        $this->assertSame('residential', $p->fresh()->category);
        $this->assertSame(['Residential', 'Exterior'], $p->categoryLabels());
        $this->assertSame('Residential', $p->categoryLabel());
        // both categories now read on the card's meta line
        $this->assertSame('Residential · Exterior · Erbil · 2024', $p->metaLabel());
    }

    public function test_a_single_category_still_fills_the_list(): void
    {
        // Seeders and older code set only `category` — the list follows it.
        $p = $this->project(['category' => 'cultural']);

        $this->assertSame(['cultural'], $p->fresh()->categories);
        $this->assertSame(['cultural'], $p->categoryKeys());
    }

    public function test_duplicate_and_empty_picks_are_cleaned_up(): void
    {
        $p = $this->project(['categories' => ['cultural', 'cultural', '', 'urban']]);

        $this->assertSame(['cultural', 'urban'], $p->fresh()->categories);
    }

    public function test_search_text_covers_every_category_in_both_languages(): void
    {
        $p = $this->project([
            'categories' => ['residential', 'cultural'],
            'typology' => 'Private Villa',
        ]);

        $hay = $p->searchText();
        foreach (['villa', 'residential', 'cultural', 'کولتووری', 'erbil'] as $needle) {
            $this->assertStringContainsString($needle, $hay);
        }
    }

    public function test_the_public_grid_exposes_every_category_for_filtering(): void
    {
        Category::create(['key' => 'exterior', 'name' => 'Exterior', 'sort_order' => 90]);
        $this->project([
            'categories' => ['residential', 'exterior'],
            'imgs' => ['https://ex.com/a.jpg'],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-categories="residential exterior"', false)
            // the filter row offers the new category
            ->assertSee('data-filter="exterior"', false);
    }

    public function test_the_admin_form_creates_and_edits_a_multi_category_project(): void
    {
        Category::create(['key' => 'exterior', 'name' => 'Exterior', 'sort_order' => 90]);
        $this->actingAs($this->admin());

        Livewire::test(CreateProject::class)
            ->fillForm([
                'name' => 'Twin House',
                'categories' => ['residential', 'exterior'],
                'status' => 'Completed',
                'size' => 'default',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $p = Project::where('name', 'Twin House')->sole();
        $this->assertSame(['residential', 'exterior'], $p->categories);

        // the edit form round-trips the list
        Livewire::test(EditProject::class, ['record' => $p->getRouteKey()])
            ->assertFormSet(['categories' => ['residential', 'exterior']])
            ->fillForm(['categories' => ['cultural']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['cultural'], $p->fresh()->categories);
        $this->assertSame('cultural', $p->fresh()->category);
    }

    public function test_at_least_one_category_is_required(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateProject::class)
            ->fillForm([
                'name' => 'No Category',
                'categories' => [],
                'status' => 'Completed',
                'size' => 'default',
            ])
            ->call('create')
            ->assertHasFormErrors(['categories']);
    }

    public function test_the_admin_list_filters_by_any_of_the_selected_categories(): void
    {
        Category::create(['key' => 'exterior', 'name' => 'Exterior', 'sort_order' => 90]);
        $this->project(['name' => 'Villa', 'categories' => ['residential', 'exterior']]);
        $this->project(['name' => 'Museum', 'categories' => ['cultural']]);

        // the secondary category is enough to match
        $matches = Project::whereJsonContains('categories', 'exterior')->pluck('name')->all();
        $this->assertSame(['Villa'], $matches);
    }
}
