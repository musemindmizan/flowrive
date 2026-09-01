<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Following a task. Watchers are who gets notified when it changes, so this
 * is a real subscription rather than a bookmark.
 */
class TaskWatcherController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('view', $task->project);

        $task->watchers()->syncWithoutDetaching([$request->user()->id]);

        return Flash::success(back(), "You're watching {$task->key()}.");
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('view', $task->project);

        $task->watchers()->detach($request->user()->id);

        return Flash::info(back(), "You stopped watching {$task->key()}.");
    }
}
