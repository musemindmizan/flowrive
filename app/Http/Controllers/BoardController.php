<?php

namespace App\Http\Controllers;

use App\Models\BoardColumn;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Kanban board for one project.
 *
 * Cards are loaded in a single query ordered by (column, position) — the exact
 * index the schema declares — and grouped in PHP, rather than one query per
 * column.
 */
class BoardController extends Controller
{
    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $project->load(['boardColumns', 'members:id,name']);

        $tasks = Task::query()
            ->where('project_id', $project->id)
            ->with(['assignee:id,name', 'labels:id,name,color'])
            ->orderBy('board_column_id')
            ->orderBy('position')
            ->get()
            ->groupBy('board_column_id');

        return Inertia::render('projects/board', [
            'project' => [
                'id' => $project->id,
                'key' => $project->key,
                'name' => $project->name,
                'status' => $project->status->value,
                'statusLabel' => $project->status->label(),
                'statusTone' => $project->status->tone(),
                'progress' => $project->progress(),
                'tasksTotal' => $project->tasks_total,
                'tasksCompleted' => $project->tasks_completed,
                'deadline' => $project->deadline?->toDateString(),
                'members' => $project->members
                    ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
                    ->values()
                    ->all(),
            ],
            'columns' => $project->boardColumns
                ->map(fn (BoardColumn $column) => [
                    'id' => $column->id,
                    'name' => $column->name,
                    'category' => $column->category->value,
                    'tone' => $column->category->tone(),
                    'wipLimit' => $column->wip_limit,
                    'tasks' => $tasks->get($column->id, collect())
                        ->map(fn (Task $task) => $this->presentTask($task, $project))
                        ->values()
                        ->all(),
                ])
                ->all(),
            'canContribute' => $request->user()->can('contribute', $project),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentTask(Task $task, Project $project): array
    {
        return [
            'id' => $task->id,
            'key' => $project->key.'-'.$task->number,
            'title' => $task->title,
            'priority' => $task->priority->value,
            'priorityLabel' => $task->priority->label(),
            'priorityTone' => $task->priority->tone(),
            'priorityIcon' => $task->priority->icon(),
            'dueDate' => $task->due_date?->toDateString(),
            'assignee' => $task->assignee
                ? ['id' => $task->assignee->id, 'name' => $task->assignee->name]
                : null,
            'labels' => $task->labels
                ->map(fn ($label) => ['id' => $label->id, 'name' => $label->name, 'color' => $label->color])
                ->values()
                ->all(),
            'comments' => $task->comments_count,
            'attachments' => $task->attachments_count,
            'subtasksTotal' => $task->subtasks_total,
            'subtasksCompleted' => $task->subtasks_completed,
        ];
    }
}
