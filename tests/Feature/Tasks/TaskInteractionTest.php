<?php

namespace Tests\Feature\Tasks;

use App\Enums\ColumnCategory;
use App\Enums\MemberStatus;
use App\Enums\Priority;
use App\Enums\WorkspaceRole;
use App\Mail\WorkspaceInvitation;
use App\Models\Attachment;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskInteractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_watching_and_unwatching_a_task_persists()
    {
        [$user, $task] = $this->task();
        $task->watchers()->detach();

        $this->actingAs($user)->post(route('tasks.watch', $task))->assertRedirect();
        $this->assertTrue($task->watchers()->where('users.id', $user->id)->exists());

        $this->actingAs($user)->delete(route('tasks.unwatch', $task))->assertRedirect();
        $this->assertFalse($task->watchers()->where('users.id', $user->id)->exists());
    }

    public function test_a_reaction_toggles_rather_than_stacking()
    {
        [$user, $task] = $this->task();
        $comment = $task->allComments()->create([
            'user_id' => $user->id, 'body' => 'Nice work.',
        ]);

        $this->actingAs($user)->post(route('comments.react', $comment));
        $this->assertSame(1, $comment->reactions()->count());

        // Pressing it again removes it — one row per person per emoji
        $this->actingAs($user)->post(route('comments.react', $comment));
        $this->assertSame(0, $comment->reactions()->count());
    }

    public function test_uploading_a_file_stores_it_and_updates_the_counter()
    {
        Storage::fake();
        [$user, $task] = $this->task();

        $this->actingAs($user)
            ->post(route('attachments.store', $task), [
                'file' => UploadedFile::fake()->create('brief.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect();

        $attachment = Attachment::firstOrFail();
        $this->assertSame('brief.pdf', $attachment->original_name);
        $this->assertSame($task->project_id, $attachment->project_id);
        $this->assertNotNull($attachment->checksum);
        Storage::assertExists($attachment->path);

        $this->assertSame(1, $task->fresh()->attachments_count);
        $this->assertDatabaseHas('activities', ['verb' => 'attachment.uploaded']);
    }

    public function test_an_upload_beyond_the_workspace_quota_is_refused()
    {
        Storage::fake();
        [$user, $task] = $this->task();

        // Leave room for only a very small file
        $task->workspace->forceFill(['storage_limit_bytes' => 1024])->save();

        $this->actingAs($user)
            ->post(route('attachments.store', $task), [
                'file' => UploadedFile::fake()->create('big.pdf', 50),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Attachment::count());
    }

    public function test_deleting_an_attachment_removes_the_row_and_the_file()
    {
        Storage::fake();
        [$user, $task] = $this->task();

        $this->actingAs($user)->post(route('attachments.store', $task), [
            'file' => UploadedFile::fake()->create('notes.txt', 4),
        ]);

        $attachment = Attachment::firstOrFail();
        $path = $attachment->path;

        $this->actingAs($user)
            ->delete(route('attachments.destroy', $attachment))
            ->assertRedirect();

        $this->assertSame(0, Attachment::count());
        Storage::assertMissing($path);
        $this->assertSame(0, $task->fresh()->attachments_count);
    }

    public function test_a_viewer_cannot_upload_but_can_download()
    {
        Storage::fake();
        [$owner, $task] = $this->task();

        $this->actingAs($owner)->post(route('attachments.store', $task), [
            'file' => UploadedFile::fake()->create('shared.txt', 2),
        ]);

        $viewer = User::factory()->create();
        $task->project->workspace->members()->create([
            'user_id' => $viewer->id, 'role' => WorkspaceRole::Member,
            'status' => MemberStatus::Active, 'joined_at' => now(),
        ]);
        $task->project->members()->attach($viewer->id, ['role' => 'viewer']);

        $this->actingAs($viewer)
            ->post(route('attachments.store', $task), [
                'file' => UploadedFile::fake()->create('nope.txt', 2),
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get(route('attachments.download', Attachment::firstOrFail()))
            ->assertOk();
    }

    public function test_an_invitation_queues_an_email()
    {
        Mail::fake();
        [$user, $task] = $this->task();

        $this->actingAs($user)->post(route('invitations.store'), [
            'email' => 'rachel.moore@northwind.co',
            'role' => 'member',
        ])->assertRedirect();

        Mail::assertQueued(
            WorkspaceInvitation::class,
            fn (WorkspaceInvitation $mail) => $mail->hasTo('rachel.moore@northwind.co'),
        );
    }

    /** @return array{0: User, 1: Task} */
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
                ->firstWhere('category', ColumnCategory::Todo)->id,
            'number' => 1,
            'title' => 'Design homepage hero section',
            'priority' => Priority::High,
            'assignee_id' => $user->id,
            'reporter_id' => $user->id,
            'created_by' => $user->id,
        ]);

        return [$user, $task->fresh()];
    }
}
