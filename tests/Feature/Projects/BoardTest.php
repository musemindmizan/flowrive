<?php

namespace Tests\Feature\Projects;

use App\Enums\ColumnCategory;
use App\Enums\MemberStatus;
use App\Enums\Priority;
use App\Enums\WorkspaceRole;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_board_groups_cards_by_column_in_rank_order()
    {
        [$user, $project] = $this->board();

        $this->actingAs($user)
            ->get(route('projects.board', $project))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('projects/board')
                ->where('project.key', 'WEB')
                ->has('columns', 5)
                ->where('columns.0.name', 'Backlog')
                ->has('columns.1.tasks', 2)
                ->where('columns.1.tasks.0.title', 'First card')
                ->where('columns.1.tasks.1.title', 'Second card')
                ->where('canContribute', true)
            );
    }

    public function test_moving_a_card_writes_its_new_column_and_rank()
    {
        [$user, $project] = $this->board();
        $task = Task::where('title', 'Second card')->firstOrFail();
        $review = $project->boardColumns->firstWhere('category', ColumnCategory::Review);

        $this->actingAs($user)
            ->patch(route('tasks.move', $task), ['column' => $review->id])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame($review->id, $task->board_column_id);
        $this->assertNull($task->completed_at);
    }

    public function test_dropping_between_two_cards_lands_the_rank_between_them()
    {
        [$user, $project] = $this->board();
        $todo = $project->boardColumns->firstWhere('category', ColumnCategory::Todo);
        [$first, $second] = Task::where('board_column_id', $todo->id)->orderBy('position')->get();

        $moving = Task::where('title', 'Backlog card')->firstOrFail();

        $this->actingAs($user)->patch(route('tasks.move', $moving), [
            'column' => $todo->id,
            'before' => $first->id,
            'after' => $second->id,
        ]);

        $moving->refresh();
        $this->assertGreaterThan((float) $first->position, (float) $moving->position);
        $this->assertLessThan((float) $second->position, (float) $moving->position);
    }

    public function test_dropping_into_done_completes_the_task_and_updates_the_counters()
    {
        [$user, $project] = $this->board();
        $task = Task::where('title', 'First card')->firstOrFail();
        $done = $project->boardColumns->firstWhere('category', ColumnCategory::Done);

        $this->actingAs($user)->patch(route('tasks.move', $task), ['column' => $done->id]);

        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertSame(1, $project->fresh()->tasks_completed);
    }

    public function test_dragging_back_out_of_done_reopens_the_task()
    {
        [$user, $project] = $this->board();
        $task = Task::where('title', 'First card')->firstOrFail();
        $done = $project->boardColumns->firstWhere('category', ColumnCategory::Done);
        $todo = $project->boardColumns->firstWhere('category', ColumnCategory::Todo);

        $this->actingAs($user)->patch(route('tasks.move', $task), ['column' => $done->id]);
        $this->actingAs($user)->patch(route('tasks.move', $task), ['column' => $todo->id]);

        $this->assertNull($task->fresh()->completed_at);
        $this->assertSame(0, $project->fresh()->tasks_completed);
    }

    public function test_a_move_is_recorded_in_the_activity_log()
    {
        [$user, $project] = $this->board();
        $task = Task::where('title', 'First card')->firstOrFail();
        $review = $project->boardColumns->firstWhere('category', ColumnCategory::Review);

        $this->actingAs($user)->patch(route('tasks.move', $task), ['column' => $review->id]);

        $activity = Activity::where('verb', 'task.moved')->latest('id')->firstOrFail();
        $this->assertSame('To Do', $activity->properties['from']);
        $this->assertSame('Review', $activity->properties['to']);
        $this->assertSame($task->id, $activity->task_id);
    }

    public function test_a_card_cannot_be_moved_into_another_projects_column()
    {
        [$user, $project] = $this->board();
        $task = Task::where('title', 'First card')->firstOrFail();

        $other = $project->workspace->projects()->create([
            'key' => 'OTH',
            'name' => 'Other project',
            'lead_id' => $user->id,
            'created_by' => $user->id,
        ]);
        $other->createDefaultColumns();

        $this->actingAs($user)
            ->patch(route('tasks.move', $task), [
                'column' => $other->boardColumns->first()->id,
            ])
            ->assertSessionHasErrors('column');

        $this->assertSame($project->id, $task->fresh()->project_id);
    }

    public function test_a_viewer_cannot_move_cards()
    {
        [$user, $project] = $this->board();
        $viewer = User::factory()->create();

        $project->workspace->members()->create([
            'user_id' => $viewer->id,
            'role' => WorkspaceRole::Member,
            'status' => MemberStatus::Active,
            'joined_at' => now(),
        ]);
        $project->members()->attach($viewer->id, ['role' => 'viewer']);

        $task = Task::where('title', 'First card')->firstOrFail();
        $review = $project->boardColumns->firstWhere('category', ColumnCategory::Review);

        $this->actingAs($viewer)
            ->patch(route('tasks.move', $task), ['column' => $review->id])
            ->assertForbidden();
    }

    /**
     * A project with three cards: two in To Do, one in Backlog.
     *
     * @return array{0: User, 1: Project}
     */
    private function board(): array
    {
        $user = User::factory()->create();

        $workspace = Workspace::create([
            'name' => 'Northwind Studio',
            'slug' => 'northwind-'.uniqid(),
            'owner_id' => $user->id,
        ]);

        $workspace->members()->create([
            'user_id' => $user->id,
            'role' => WorkspaceRole::Manager,
            'status' => MemberStatus::Active,
            'joined_at' => now(),
        ]);

        $project = $workspace->projects()->create([
            'key' => 'WEB',
            'name' => 'Website Redesign',
            'lead_id' => $user->id,
            'created_by' => $user->id,
        ]);
        $project->createDefaultColumns();
        $project->members()->attach($user->id, ['role' => 'lead']);

        $project->load('boardColumns');
        $todo = $project->boardColumns->firstWhere('category', ColumnCategory::Todo);
        $backlog = $project->boardColumns->firstWhere('category', ColumnCategory::Backlog);

        $base = [
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'reporter_id' => $user->id,
            'created_by' => $user->id,
            'assignee_id' => $user->id,
            'priority' => Priority::Medium,
        ];

        Task::create($base + ['board_column_id' => $todo->id, 'number' => 1, 'title' => 'First card', 'position' => '1000']);
        Task::create($base + ['board_column_id' => $todo->id, 'number' => 2, 'title' => 'Second card', 'position' => '2000']);
        Task::create($base + ['board_column_id' => $backlog->id, 'number' => 3, 'title' => 'Backlog card', 'position' => '1000']);

        $project->recountTasks();

        return [$user, $project->fresh()->load('boardColumns')];
    }
}
