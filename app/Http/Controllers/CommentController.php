<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Comment;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Comments on a task. The counter cache on `tasks` is maintained here rather
 * than by a COUNT(*) on every board render.
 */
class CommentController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('contribute', $task->project);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
        ]);

        DB::transaction(function () use ($request, $task, $data) {
            $parent = isset($data['parent_id'])
                ? Comment::whereKey($data['parent_id'])->first()
                : null;

            // A reply belongs to the same thread; replying to a reply keeps
            // the thread one level deep rather than nesting indefinitely.
            $parentId = $parent?->task_id === $task->id
                ? ($parent->parent_id ?? $parent->id)
                : null;

            $comment = $task->allComments()->create([
                'user_id' => $request->user()->id,
                'parent_id' => $parentId,
                'body' => $data['body'],
            ]);

            $task->forceFill(['comments_count' => $task->allComments()->count()])->save();

            // Whoever joins a conversation starts following it
            $task->watchers()->syncWithoutDetaching([$request->user()->id]);

            Activity::log(
                workspaceId: $task->workspace_id,
                verb: 'comment.created',
                actorId: $request->user()->id,
                projectId: $task->project_id,
                taskId: $task->id,
                subject: $comment,
            );
        });

        return back();
    }

    public function destroy(Request $request, Comment $comment): RedirectResponse
    {
        $task = $comment->task;

        // Authors delete their own; project leads and admins can delete any
        if ($comment->user_id !== $request->user()->id) {
            $this->authorize('update', $task->project);
        } else {
            $this->authorize('contribute', $task->project);
        }

        DB::transaction(function () use ($comment, $task) {
            $comment->delete();
            $task->forceFill(['comments_count' => $task->allComments()->count()])->save();
        });

        return back();
    }
}
