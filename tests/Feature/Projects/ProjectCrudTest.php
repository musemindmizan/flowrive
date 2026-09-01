<?php

namespace Tests\Feature\Projects;

use App\Enums\ColumnCategory;
use App\Enums\MemberStatus;
use App\Enums\Priority;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_project_seeds_its_board_and_makes_the_lead_a_member()
    {
        [$user] = $this->workspace();

        $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Website Redesign',
            'key' => 'WEB',
            'status' => 'in_progress',
            'priority' => 'high',
            'visibility' => 'members',
            'color' => 'brand',
        ])->assertRedirect();

        $project = Project::where('key', 'WEB')->firstOrFail();

        // A project without columns has an unusable board
        $this->assertCount(5, $project->boardColumns);
        $this->assertSame('Backlog', $project->boardColumns->first()->name);
        $this->assertTrue($project->members->contains('id', $user->id));
        $this->assertSame($user->id, $project->lead_id);
        $this->assertDatabaseHas('activities', ['verb' => 'project.created']);
    }

    public function test_the_project_key_is_unique_per_workspace_not_globally()
    {
        [$user, $workspace] = $this->workspace();
        $this->project($workspace, $user, 'WEB');

        $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Another', 'key' => 'WEB', 'status' => 'planning',
            'priority' => 'low', 'visibility' => 'members', 'color' => 'brand',
        ])->assertSessionHasErrors('key');

        // The same key in a different workspace is fine
        $other = User::factory()->create();
        $second = Workspace::create([
            'name' => 'Other studio', 'slug' => 'other-'.uniqid(), 'owner_id' => $other->id,
        ]);
        $second->members()->create([
            'user_id' => $other->id, 'role' => WorkspaceRole::Manager,
            'status' => MemberStatus::Active, 'joined_at' => now(),
        ]);

        $this->actingAs($other)->post(route('projects.store'), [
            'name' => 'Their site', 'key' => 'WEB', 'status' => 'planning',
            'priority' => 'low', 'visibility' => 'members', 'color' => 'brand',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Project::where('key', 'WEB')->count());
    }

    public function test_a_deadline_before_the_start_date_is_rejected()
    {
        [$user] = $this->workspace();

        $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Backwards', 'key' => 'BAD', 'status' => 'planning',
            'priority' => 'low', 'visibility' => 'members', 'color' => 'brand',
            'start_date' => today()->addDays(10)->toDateString(),
            'deadline' => today()->toDateString(),
        ])->assertSessionHasErrors('deadline');
    }

    public function test_a_lowercase_key_is_accepted_and_normalised()
    {
        [$user] = $this->workspace();

        $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Mobile App', 'key' => 'mob', 'status' => 'planning',
            'priority' => 'low', 'visibility' => 'members', 'color' => 'brand',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('projects', ['key' => 'MOB']);
    }

    public function test_only_a_lead_or_admin_can_edit_a_project()
    {
        [$user, $workspace] = $this->workspace();
        $project = $this->project($workspace, $user, 'WEB');

        $member = User::factory()->create();
        $workspace->members()->create([
            'user_id' => $member->id, 'role' => WorkspaceRole::Member,
            'status' => MemberStatus::Active, 'joined_at' => now(),
        ]);
        $project->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)->get(route('projects.edit', $project))->assertForbidden();
        $this->actingAs($user)->get(route('projects.edit', $project))->assertOk();
    }

    public function test_deleting_a_project_is_admin_only_and_soft_deletes()
    {
        [$manager, $workspace] = $this->workspace();
        $project = $this->project($workspace, $manager, 'WEB');

        // The manager leads it but is not a workspace admin
        $this->actingAs($manager)
            ->delete(route('projects.destroy', $project))
            ->assertForbidden();

        $admin = User::factory()->create();
        $workspace->members()->create([
            'user_id' => $admin->id, 'role' => WorkspaceRole::Admin,
            'status' => MemberStatus::Active, 'joined_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('projects.destroy', $project))
            ->assertRedirect(route('projects.index'));

        $this->assertSoftDeleted($project);
    }

    public function test_creating_a_task_allocates_a_key_and_updates_the_project_counters()
    {
        [$user, $workspace] = $this->workspace();
        $project = $this->project($workspace, $user, 'WEB');
        $column = $project->boardColumns->firstWhere('category', ColumnCategory::Todo);

        $this->actingAs($user)->post(route('tasks.store'), [
            'project_id' => $project->id,
            'board_column_id' => $column->id,
            'title' => 'Design homepage hero section',
            'priority' => 'high',
            'assignee_id' => $user->id,
            'subtasks' => ['Moodboard', 'Draft directions'],
        ])->assertRedirect();

        $task = Task::firstOrFail();
        $this->assertSame(1, $task->number);
        $this->assertSame('WEB-1', $task->key());
        $this->assertSame(2, $task->subtasks_total);
        $this->assertSame(2, $project->fresh()->next_task_number);
        $this->assertSame(1, $project->fresh()->tasks_total);
        $this->assertTrue($task->watchers->contains('id', $user->id));
    }

    public function test_task_numbers_never_repeat_even_after_a_delete()
    {
        [$user, $workspace] = $this->workspace();
        $project = $this->project($workspace, $user, 'WEB');
        $column = $project->boardColumns->first();

        foreach (['First', 'Second'] as $title) {
            $this->actingAs($user)->post(route('tasks.store'), [
                'project_id' => $project->id,
                'board_column_id' => $column->id,
                'title' => $title,
                'priority' => 'medium',
            ]);
        }

        Task::where('title', 'Second')->firstOrFail()->delete();

        $this->actingAs($user)->post(route('tasks.store'), [
            'project_id' => $project->id,
            'board_column_id' => $column->id,
            'title' => 'Third',
            'priority' => 'medium',
        ]);

        $this->assertSame(3, Task::where('title', 'Third')->firstOrFail()->number);
    }

    public function test_a_task_cannot_be_filed_under_another_projects_column()
    {
        [$user, $workspace] = $this->workspace();
        $project = $this->project($workspace, $user, 'WEB');
        $other = $this->project($workspace, $user, 'OTH');

        $this->actingAs($user)->post(route('tasks.store'), [
            'project_id' => $project->id,
            'board_column_id' => $other->boardColumns->first()->id,
            'title' => 'Wrong column',
            'priority' => 'medium',
        ])->assertStatus(422);
    }

    public function test_creating_a_task_straight_into_done_marks_it_complete()
    {
        [$user, $workspace] = $this->workspace();
        $project = $this->project($workspace, $user, 'WEB');
        $done = $project->boardColumns->firstWhere('category', ColumnCategory::Done);

        $this->actingAs($user)->post(route('tasks.store'), [
            'project_id' => $project->id,
            'board_column_id' => $done->id,
            'title' => 'Already finished',
            'priority' => 'low',
        ]);

        $this->assertNotNull(Task::firstOrFail()->completed_at);
        $this->assertSame(1, $project->fresh()->tasks_completed);
    }

    /** @return array{0: User, 1: Workspace} */
    private function workspace(): array
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

        return [$user, $workspace];
    }

    private function project(Workspace $workspace, User $lead, string $key): Project
    {
        $project = $workspace->projects()->create([
            'key' => $key, 'name' => $key.' project',
            'priority' => Priority::Medium,
            'lead_id' => $lead->id, 'created_by' => $lead->id,
        ]);
        $project->createDefaultColumns();
        $project->members()->attach($lead->id, ['role' => 'lead']);

        return $project->load('boardColumns', 'members');
    }
}
