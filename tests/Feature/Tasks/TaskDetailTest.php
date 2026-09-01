<?php

namespace Tests\Feature\Tasks;

use App\Enums\ColumnCategory;
use App\Enums\MemberStatus;
use App\Enums\Priority;
use App\Enums\WorkspaceRole;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TaskDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_detail_page_carries_the_thread_checklist_and_history()
    {
        [$user, $task] = $this->task();

        $this->actingAs($user)
            ->get(route('tasks.show', $task))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('tasks/show')
                ->where('task.key', 'WEB-1')
                ->where('task.title', 'Design homepage hero section')
                ->where('task.assignee.name', $user->name)
                ->has('subtasks', 2)
                ->has('comments', 1)
                ->has('comments.0.replies', 1)
                ->where('canContribute', true)
            );
    }

    public function test_someone_outside_the_project_cannot_open_the_task()
    {
        [, $task] = $this->task();

        $outsider = User::factory()->create();
        $task->project->workspace->members()->create([
            'user_id' => $outsider->id, 'role' => WorkspaceRole::Member,
            'status' => MemberStatus::Active, 'joined_at' => now(),
        ]);

        $this->actingAs($outsider)
            ->get(route('tasks.show', $task))
            ->assertForbidden();
    }

    public function test_posting_a_comment_stores_it_and_updates_the_counter()
    {
        [$user, $task] = $this->task();

        $this->actingAs($user)
            ->post(route('comments.store', $task), ['body' => 'Looks good to me.'])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'body' => 'Looks good to me.',
        ]);

        // 1 existing + 1 reply + the new one
        $this->assertSame(3, $task->fresh()->comments_count);
    }

    public function test_commenting_makes_you_a_watcher()
    {
        [, $task] = $this->task();

        $member = User::factory()->create();
        $task->project->workspace->members()->create([
            'user_id' => $member->id, 'role' => WorkspaceRole::Member,
            'status' => MemberStatus::Active, 'joined_at' => now(),
        ]);
        $task->project->members()->attach($member->id, ['role' => 'member']);

        $this->assertFalse($task->watchers()->where('users.id', $member->id)->exists());

        $this->actingAs($member)
            ->post(route('comments.store', $task), ['body' => 'Joining in.']);

        $this->assertTrue($task->watchers()->where('users.id', $member->id)->exists());
    }

    public function test_replies_stay_one_level_deep()
    {
        [$user, $task] = $this->task();
        $reply = Comment::where('body', 'A reply.')->firstOrFail();

        // Replying to a reply attaches to the same top-level comment
        $this->actingAs($user)->post(route('comments.store', $task), [
            'body' => 'Reply to a reply.',
            'parent_id' => $reply->id,
        ]);

        $created = Comment::where('body', 'Reply to a reply.')->firstOrFail();
        $this->assertSame($reply->parent_id, $created->parent_id);
    }

    public function test_a_blank_comment_is_rejected()
    {
        [$user, $task] = $this->task();

        $this->actingAs($user)
            ->post(route('comments.store', $task), ['body' => ''])
            ->assertSessionHasErrors('body');
    }

    public function test_you_can_delete_your_own_comment_but_not_someone_elses()
    {
        [$user, $task] = $this->task();
        $mine = $task->allComments()->create(['user_id' => $user->id, 'body' => 'Mine']);

        $other = User::factory()->create();
        $task->project->workspace->members()->create([
            'user_id' => $other->id, 'role' => WorkspaceRole::Member,
            'status' => MemberStatus::Active, 'joined_at' => now(),
        ]);
        $task->project->members()->attach($other->id, ['role' => 'member']);
        $theirs = $task->allComments()->create(['user_id' => $other->id, 'body' => 'Theirs']);

        $this->actingAs($other)
            ->delete(route('comments.destroy', $theirs))
            ->assertRedirect();
        $this->assertSoftDeleted($theirs);

        // A plain member cannot remove the lead's comment
        $this->actingAs($other)
            ->delete(route('comments.destroy', $mine))
            ->assertForbidden();
    }

    public function test_ticking_a_subtask_updates_both_task_counters()
    {
        [$user, $task] = $this->task();
        $subtask = $task->subtasks()->where('is_completed', false)->firstOrFail();

        $this->actingAs($user)
            ->patch(route('subtasks.update', $subtask), ['is_completed' => true])
            ->assertRedirect();

        $this->assertTrue($subtask->fresh()->is_completed);
        $this->assertSame($user->id, $subtask->fresh()->completed_by);

        $task->refresh();
        $this->assertSame(2, $task->subtasks_total);
        $this->assertSame(2, $task->subtasks_completed);
    }

    public function test_a_viewer_cannot_comment_or_tick_subtasks()
    {
        [, $task] = $this->task();

        $viewer = User::factory()->create();
        $task->project->workspace->members()->create([
            'user_id' => $viewer->id, 'role' => WorkspaceRole::Member,
            'status' => MemberStatus::Active, 'joined_at' => now(),
        ]);
        $task->project->members()->attach($viewer->id, ['role' => 'viewer']);

        $this->actingAs($viewer)
            ->post(route('comments.store', $task), ['body' => 'Nope'])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->patch(route('subtasks.update', $task->subtasks()->first()), ['is_completed' => true])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get(route('tasks.show', $task))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canContribute', false));
    }

    /**
     * One task with two subtasks (one done), a comment and one reply.
     *
     * @return array{0: User, 1: Task}
     */
    private function task(): array
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

        /** @var Project $project */
        $project = $workspace->projects()->create([
            'key' => 'WEB', 'name' => 'Website Redesign',
            'lead_id' => $user->id, 'created_by' => $user->id,
        ]);
        $project->createDefaultColumns();
        $project->members()->attach($user->id, ['role' => 'lead']);
        $project->load('boardColumns');

        $task = Task::create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'board_column_id' => $project->boardColumns
                ->firstWhere('category', ColumnCategory::InProgress)->id,
            'number' => 1,
            'title' => 'Design homepage hero section',
            'description' => 'Explore three directions for the hero.',
            'priority' => Priority::High,
            'assignee_id' => $user->id,
            'reporter_id' => $user->id,
            'created_by' => $user->id,
            'due_date' => today()->addDays(2),
        ]);

        Subtask::create(['task_id' => $task->id, 'title' => 'Moodboard', 'is_completed' => true, 'position' => 0]);
        Subtask::create(['task_id' => $task->id, 'title' => 'Draft directions', 'is_completed' => false, 'position' => 1]);
        $task->forceFill(['subtasks_total' => 2, 'subtasks_completed' => 1])->save();

        $comment = $task->allComments()->create(['user_id' => $user->id, 'body' => 'A comment.']);
        $task->allComments()->create([
            'user_id' => $user->id, 'parent_id' => $comment->id, 'body' => 'A reply.',
        ]);
        $task->forceFill(['comments_count' => 2])->save();

        return [$user, $task->fresh()];
    }
}
