<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\BoardColumn;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moving a card on the board.
 *
 * The client sends the two neighbours it dropped between rather than a list
 * of every card in the column, so one drag is one UPDATE — the whole point of
 * a fractional `position`.
 */
class TaskMoveController extends Controller
{
    public function __invoke(Request $request, Task $task): RedirectResponse
    {
        $project = $task->project;

        $this->authorize('contribute', $project);

        $data = $request->validate([
            'column' => ['required', 'integer', 'exists:board_columns,id'],
            'before' => ['nullable', 'integer', 'exists:tasks,id'],
            'after' => ['nullable', 'integer', 'exists:tasks,id'],
        ]);

        $column = BoardColumn::whereKey($data['column'])->firstOrFail();

        // A card can only be dropped on a column of its own project
        if ($column->project_id !== $task->project_id) {
            throw ValidationException::withMessages([
                'column' => 'That column belongs to a different project.',
            ]);
        }

        $from = $task->boardColumn;

        DB::transaction(function () use ($task, $column, $data, $from) {
            $before = isset($data['before'])
                ? Task::whereKey($data['before'])->first()?->position
                : null;
            $after = isset($data['after'])
                ? Task::whereKey($data['after'])->first()?->position
                : null;

            $task->board_column_id = $column->id;
            $task->position = Task::positionBetween($before, $after);

            // The Done column is what "finished" means — completed_at follows
            // the card rather than being set by a separate action.
            if ($column->category->isDone() && $task->completed_at === null) {
                $task->completed_at = now();
            } elseif (! $column->category->isDone()) {
                $task->completed_at = null;
            }

            $task->save();

            $task->project->recountTasks();

            Activity::log(
                workspaceId: $task->workspace_id,
                verb: 'task.moved',
                actorId: request()->user()?->id,
                projectId: $task->project_id,
                taskId: $task->id,
                subject: $task,
                properties: ['from' => $from->name, 'to' => $column->name],
            );
        });

        return back();
    }
}
