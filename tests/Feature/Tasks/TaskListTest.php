<?php

namespace Tests\Feature\Tasks;

use App\Enums\ColumnCategory;
use App\Enums\MemberStatus;
use App\Enums\Priority;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TaskListTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_shows_only_tasks_from_projects_the_viewer_can_reach()
    {
        [$user, $project] = $this->tasks();

        $hidden = $project->workspace->projects()->create([
            'key' => 'HID', 'name' => 'Hidden',
            'lead_id' => $project->lead_id, 'created_by' => $project->lead_id,
        ]);
        $hidden->createDefaultColumns();
        Task::create([
            'workspace_id' => $project->workspace_id,
            'project_id' => $hidden->id,
            'board_column_id' => $hidden->boardColumns->first()->id,
            'number' => 1,
            'title' => 'Invisible task',
            'priority' => Priority::Low,
            'reporter_id' => $project->lead_id,
            'created_by' => $project->lead_id,
        ]);

        $member = User::factory()->create();
        $project->workspace->members()->create([
            'user_id' => $member->id, 'role' => WorkspaceRole::Member,
            'status' => MemberStatus::Active, 'joined_at' => now(),
        ]);
        $project->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)
            ->get(route('tasks.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('tasks/index')
                ->has('tasks', 4)
                ->where('counts.all', 4)
            );
    }

    public function test_the_scope_tabs_narrow_the_list()
    {
        [$user] = $this->tasks();

        $this->actingAs($user)
            ->get(route('tasks.index', ['scope' => 'overdue']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('tasks', 1)
                ->where('tasks.0.title', 'Overdue work')
            );

        $this->actingAs($user)
            ->get(route('tasks.index', ['scope' => 'done']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('tasks', 1)
                ->where('tasks.0.isComplete', true)
            );
    }

    public function test_status_filters_use_the_column_category_not_its_name()
    {
        [$user, $project] = $this->tasks();

        $review = $project->boardColumns->firstWhere('category', ColumnCategory::Review);
        $review->update(['name' => 'Needs a second pair of eyes']);

        $this->actingAs($user)
            ->get(route('tasks.index', ['status' => ['review']]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('tasks', 1)
                ->where('tasks.0.status', 'Needs a second pair of eyes')
            );
    }

    public function test_priority_filter_and_search_combine()
    {
        [$user] = $this->tasks();

        $this->actingAs($user)
            ->get(route('tasks.index', ['priority' => ['critical'], 'search' => 'overdue']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('tasks', 1));

        $this->actingAs($user)
            ->get(route('tasks.index', ['priority' => ['low'], 'search' => 'overdue']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('tasks', 0));
    }

    public function test_tasks_without_a_due_date_sort_last_in_both_directions()
    {
        [$user] = $this->tasks();

        foreach (['asc', 'desc'] as $direction) {
            $this->actingAs($user)
                ->get(route('tasks.index', ['sort' => 'due', 'direction' => $direction]))
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('tasks.3.title', 'No due date')
                );
        }
    }

    public function test_the_list_paginates()
    {
        [$user, $project] = $this->tasks();

        for ($i = 10; $i < 26; $i++) {
            Task::create([
                'workspace_id' => $project->workspace_id,
                'project_id' => $project->id,
                'board_column_id' => $project->boardColumns->first()->id,
                'number' => $i,
                'title' => "Filler task {$i}",
                'priority' => Priority::Low,
                'reporter_id' => $user->id,
                'created_by' => $user->id,
                'due_date' => today()->addDays($i),
            ]);
        }

        $this->actingAs($user)
            ->get(route('tasks.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('tasks', 15)
                ->where('meta.total', 20)
                ->where('meta.lastPage', 2)
                ->where('meta.from', 1)
            );

        $this->actingAs($user)
            ->get(route('tasks.index', ['page' => 2]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('tasks', 5)
                ->where('meta.currentPage', 2)
            );
    }

    /**
     * Four tasks: one overdue and critical, one in review, one completed,
     * one with no due date.
     *
     * @return array{0: User, 1: Project}
     */
    private function tasks(): array
    {
        $user = User::factory()->create();

        $workspace = Workspace::create([
            'name' => 'Northwind Studio',
            'slug' => 'northwind-'.uniqid(),
            'owner_id' => $user->id,
        ]);
        $workspace->members()->create([
            'user_id' => $user->id, 'role' => WorkspaceRole::Manager,
            'status' => MemberStatus::Active, 'joined_at' => now(),
        ]);

        $project = $workspace->projects()->create([
            'key' => 'WEB', 'name' => 'Website Redesign',
            'lead_id' => $user->id, 'created_by' => $user->id,
        ]);
        $project->createDefaultColumns();
        $project->members()->attach($user->id, ['role' => 'lead']);
        $project->load('boardColumns');

        $todo = $project->boardColumns->firstWhere('category', ColumnCategory::Todo);
        $review = $project->boardColumns->firstWhere('category', ColumnCategory::Review);
        $done = $project->boardColumns->firstWhere('category', ColumnCategory::Done);

        $base = [
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'reporter_id' => $user->id,
            'created_by' => $user->id,
            'assignee_id' => $user->id,
        ];

        Task::create($base + ['board_column_id' => $todo->id, 'number' => 1, 'title' => 'Overdue work', 'priority' => Priority::Critical, 'due_date' => today()->subDays(3)]);
        Task::create($base + ['board_column_id' => $review->id, 'number' => 2, 'title' => 'In review', 'priority' => Priority::High, 'due_date' => today()->addDays(2)]);
        Task::create($base + ['board_column_id' => $done->id, 'number' => 3, 'title' => 'Finished', 'priority' => Priority::Medium, 'due_date' => today()->addDays(5), 'completed_at' => now()]);
        Task::create($base + ['board_column_id' => $todo->id, 'number' => 4, 'title' => 'No due date', 'priority' => Priority::Low]);

        $project->recountTasks();

        return [$user, $project->fresh()->load('boardColumns')];
    }
}
