<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The order projects appear in on the public grid is `sort_order`, which the
 * admin panel edits by dragging. Dragging is fine for small nudges, so the
 * table also offers "move to start" / "move to end" for the two jumps that a
 * drag across a paginated list cannot make.
 */
class ProjectOrderingTest extends TestCase
{
    use RefreshDatabase;

    /** Four published projects, already in order 1..4. */
    private function projects(int $count = 4): Collection
    {
        return collect(range(1, $count))->map(fn ($i) => Project::create([
            'name' => "Project {$i}",
            'status' => 'Completed',
            'size' => 'default',
            'city' => 'Erbil',
            'imgs' => ['https://ex.com/a.jpg'],
            'sort_order' => $i,
            'is_published' => true,
        ]));
    }

    /** The names in the order the public site would render them. */
    private function order(): array
    {
        return Project::publishedOrdered()->pluck('name')->all();
    }

    public function test_a_project_can_be_moved_to_the_start(): void
    {
        $this->projects()->get(2)->moveToTop();

        $this->assertSame(
            ['Project 3', 'Project 1', 'Project 2', 'Project 4'],
            $this->order(),
        );
    }

    public function test_a_project_can_be_moved_to_the_end(): void
    {
        $this->projects()->get(0)->moveToBottom();

        $this->assertSame(
            ['Project 2', 'Project 3', 'Project 4', 'Project 1'],
            $this->order(),
        );
    }

    public function test_moving_leaves_the_list_numbered_without_gaps(): void
    {
        // Older edits can leave every row sharing one position; a move should
        // tidy that up rather than build on top of it.
        $this->projects();
        Project::query()->update(['sort_order' => 7]);

        Project::where('name', 'Project 4')->first()->moveToTop();

        $this->assertSame([1, 2, 3, 4], Project::orderBy('sort_order')->pluck('sort_order')->all());
        $this->assertSame('Project 4', $this->order()[0]);
    }

    public function test_an_unpublished_project_does_not_take_a_slot_on_the_grid(): void
    {
        $projects = $this->projects();
        $projects->get(1)->update(['is_published' => false]);

        $projects->get(3)->moveToTop();

        // Ordering spans every project, but the grid only shows the live ones.
        $this->assertSame(['Project 4', 'Project 1', 'Project 3'], $this->order());
    }

    public function test_the_public_grid_renders_in_the_new_order(): void
    {
        $this->projects()->get(3)->moveToTop();

        $payload = $this->get('/')->assertOk()->viewData('payload');

        $this->assertSame(
            ['Project 4', 'Project 1', 'Project 2', 'Project 3'],
            collect($payload['projects'])->pluck('name')->all(),
        );
    }

    public function test_an_explicit_order_can_be_applied_in_one_go(): void
    {
        // What the Project Grid page sends when tiles have been dragged around.
        $ids = $this->projects()->pluck('id')->all();
        Project::applyOrder([$ids[2], $ids[0], $ids[3], $ids[1]]);

        $this->assertSame(
            ['Project 3', 'Project 1', 'Project 4', 'Project 2'],
            $this->order(),
        );
    }

    public function test_projects_left_out_of_the_layout_keep_their_order_behind_it(): void
    {
        // The grid editor only posts the tiles it can draw, so anything hidden
        // from it must not be shuffled — only pushed behind the visible ones.
        $projects = $this->projects();
        $hidden = $projects->get(1);
        $hidden->update(['is_published' => false]);

        Project::applyOrder([$projects->get(3)->id, $projects->get(0)->id, $projects->get(2)->id]);

        $this->assertSame(['Project 4', 'Project 1', 'Project 3'], $this->order());
        // the hidden one is renumbered last, not lost or interleaved
        $this->assertSame(4, $hidden->fresh()->sort_order);
    }
}
