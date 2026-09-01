<?php

namespace App\Http\Controllers;

use App\Models\Subtask;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Checklist items. Ticking one recomputes the parent task's two counters, so
 * the board card's progress bar never drifts from the list beneath it.
 */
class SubtaskController extends Controller
{
    public function update(Request $request, Subtask $subtask): RedirectResponse
    {
        $task = $subtask->task;

        $this->authorize('contribute', $task->project);

        $data = $request->validate([
            'is_completed' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $subtask, $data, $task) {
            $subtask->forceFill([
                'is_completed' => $data['is_completed'],
                'completed_at' => $data['is_completed'] ? now() : null,
                'completed_by' => $data['is_completed'] ? $request->user()->id : null,
            ])->save();

            $task->forceFill([
                'subtasks_total' => $task->subtasks()->count(),
                'subtasks_completed' => $task->subtasks()->where('is_completed', true)->count(),
            ])->save();
        });

        return back();
    }

    public function store(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('contribute', $task->project);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
        ]);

        DB::transaction(function () use ($task, $data) {
            $task->subtasks()->create([
                'title' => $data['title'],
                'position' => (int) $task->subtasks()->max('position') + 1,
            ]);

            $task->forceFill(['subtasks_total' => $task->subtasks()->count()])->save();
        });

        return back();
    }
}
