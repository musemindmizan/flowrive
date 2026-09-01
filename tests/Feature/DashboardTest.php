<?php

namespace Tests\Feature;

use App\Enums\ColumnCategory;
use App\Enums\MemberStatus;
use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Enums\WorkspaceRole;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_a_user_without_a_workspace_sees_an_empty_state_rather_than_an_error()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('dashboard')
                ->where('workspace', null)
                ->has('greeting')
            );
    }

    public function test_the_dashboard_reports_real_counts_for_the_workspace()
    {
        [$user, $workspace, $project] = $this->workspaceWithWork();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('dashboard')
                ->where('workspace.name', $workspace->name)
                ->where('greeting.name', 'Jordan')
                // 3 tasks: 2 open (one assigned to Jordan), 1 completed
                ->where('stats.1.value', 2)
                ->where('stats.2.value', 1)
                ->where('stats.3.value', 1)
                ->has('projects', 1)
                ->where('projects.0.key', $project->key)
                ->where('projects.0.progress', 33)
                ->has('myTasks', 2)
                ->where('myTasks.0.key', $project->key.'-1')
                ->has('activity', 1)
                ->has('workload', 1)
                ->where('workload.0.open', 2)
            );
    }

    public function test_deadlines_merge_tasks_and_projects_in_date_order()
    {
        [$user, , $project] = $this->workspaceWithWork();

        // The project deadline falls between the two task due dates
        $project->update(['deadline' => today()->addDays(3)]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('deadlines.0.type', 'task')
                ->where('deadlines.1.type', 'project')
                ->where('deadlines.1.meta', 'Project deadline')
            );
    }

    public function test_only_the_signed_in_users_tasks_appear_under_my_tasks()
    {
        [$user, , $project] = $this->workspaceWithWork();
        $other = User::factory()->create();

        Task::query()->where('assignee_id', $user->id)->update(['assignee_id' => $other->id]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('myTasks', 0));
    }

    /**
     * One workspace, one project, three tasks — small enough to assert exact
     * numbers against.
     *
     * @return array{0: User, 1: Workspace, 2: Project}
     */
    private function workspaceWithWork(): array
    {
        $user = User::factory()->create(['name' => 'Jordan Ellis']);

        $workspace = Workspace::create([
            'name' => 'Northwind Studio',
            'slug' => 'northwind',
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
            'status' => ProjectStatus::InProgress,
            'priority' => Priority::High,
            'deadline' => today()->addDays(10),
            'lead_id' => $user->id,
            'created_by' => $user->id,
        ]);
        $project->createDefaultColumns();
        $project->members()->attach($user->id, ['role' => 'lead']);

        $todo = $project->boardColumns->firstWhere('category', ColumnCategory::Todo);
        $done = $project->boardColumns->firstWhere('category', ColumnCategory::Done);

        $base = [
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'reporter_id' => $user->id,
            'created_by' => $user->id,
            'assignee_id' => $user->id,
            'priority' => Priority::High,
        ];

        Task::create($base + [
            'board_column_id' => $todo->id,
            'number' => 1,
            'title' => 'Design homepage hero section',
            'due_date' => today()->addDay(),
        ]);

        Task::create($base + [
            'board_column_id' => $todo->id,
            'number' => 2,
            'title' => 'Create responsive navigation',
            'due_date' => today()->addDays(6),
        ]);

        Task::create($base + [
            'board_column_id' => $done->id,
            'number' => 3,
            'title' => 'Migrate blog content',
            'due_date' => today()->subDay(),
            'completed_at' => now(),
        ]);

        $project->recountTasks();

        Activity::log(
            workspaceId: $workspace->id,
            verb: 'task.created',
            actorId: $user->id,
            projectId: $project->id,
        );

        return [$user->fresh(), $workspace, $project];
    }
}
