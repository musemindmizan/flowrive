<?php

namespace App\Http\Controllers;

use App\Enums\ColumnCategory;
use App\Enums\MemberStatus;
use App\Enums\Priority;
use App\Http\Requests\TaskRequest;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\BoardColumn;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use App\Support\Flash;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use SortDirection;

/**
 * The task list across every project the viewer can reach.
 *
 * Status is filtered by the board column's *category* rather than its name:
 * columns are renameable per project, the category is not, so "show me
 * everything in review" keeps working across projects that call it something
 * else.
 */
class TaskController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'scope' => ['nullable', 'in:all,mine,overdue,done'],
            'project' => ['nullable', 'integer'],
            'status' => ['nullable', 'array'],
            'status.*' => ['string'],
            'priority' => ['nullable', 'array'],
            'priority.*' => ['string'],
            'sort' => ['nullable', 'in:due,title,priority,created'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $user = $request->user();

        $scope = $filters['scope'] ?? 'all';
        $search = $filters['search'] ?? '';
        $sort = $filters['sort'] ?? 'due';
        $directionName = $filters['direction'] ?? 'asc';
        // orderBy() takes the native SortDirection enum, so the validated
        // string is narrowed once here rather than passed around loose.
        $direction = $directionName === 'desc'
            ? SortDirection::Descending
            : SortDirection::Ascending;

        $visibleProjects = Project::query()->visibleTo($user)->select('id');

        $tasks = Task::query()
            ->whereIn('project_id', $visibleProjects)
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $q) => $q
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('number', 'like', "%{$search}%")
            ))
            ->when(
                isset($filters['project']),
                fn (Builder $query) => $query->where('project_id', $filters['project'])
            )
            ->when(
                $filters['status'] ?? [],
                fn (Builder $query, array $statuses) => $query->whereHas(
                    'boardColumn',
                    fn (Builder $column) => $column->whereIn('category', $statuses)
                )
            )
            ->when(
                $filters['priority'] ?? [],
                fn (Builder $query, array $priorities) => $query->whereIn('priority', $priorities)
            )
            ->tap(fn (Builder $query) => $this->applyScope($query, $scope, $user))
            ->tap(fn (Builder $query) => $this->applySort($query, $sort, $direction))
            ->with(['project:id,key,name', 'assignee:id,name', 'boardColumn:id,name,category', 'labels:id,name'])
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('tasks/index', [
            'tasks' => $this->present($tasks),
            'meta' => [
                'from' => $tasks->firstItem(),
                'to' => $tasks->lastItem(),
                'total' => $tasks->total(),
                'currentPage' => $tasks->currentPage(),
                'lastPage' => $tasks->lastPage(),
            ],
            'filters' => [
                'search' => $search,
                'scope' => $scope,
                'project' => $filters['project'] ?? null,
                'status' => $filters['status'] ?? [],
                'priority' => $filters['priority'] ?? [],
                'sort' => $sort,
                'direction' => $directionName,
            ],
            'counts' => $this->counts($user, $visibleProjects),
            'projectOptions' => Project::query()
                ->visibleTo($user)
                ->orderBy('name')
                ->get(['id', 'key', 'name'])
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'key' => $project->key,
                    'name' => $project->name,
                ])
                ->all(),
            'statusOptions' => collect(ColumnCategory::cases())
                ->map(fn (ColumnCategory $category) => [
                    'value' => $category->value,
                    'label' => $category->defaultName(),
                ])
                ->all(),
            'priorityOptions' => collect(Priority::cases())
                ->map(fn (Priority $priority) => [
                    'value' => $priority->value,
                    'label' => $priority->label(),
                ])
                ->all(),
        ]);
    }

    /**
     * @param  Builder<Task>  $query
     */
    private function applyScope(Builder $query, string $scope, User $user): void
    {
        match ($scope) {
            'mine' => $query->where('assignee_id', $user->id)->whereNull('completed_at'),
            'overdue' => $query->whereNull('completed_at')->whereDate('due_date', '<', today()),
            'done' => $query->whereNotNull('completed_at'),
            default => null,
        };
    }

    /**
     * @param  Builder<Task>  $query
     */
    private function applySort(Builder $query, string $sort, SortDirection $direction): void
    {
        match ($sort) {
            'title' => $query->orderBy('title', $direction),
            'priority' => $query->orderByRaw(
                "FIELD(priority, 'critical', 'high', 'medium', 'low') "
                .($direction === SortDirection::Ascending ? 'asc' : 'desc')
            ),
            'created' => $query->orderBy('id', $direction),
            // Tasks with no due date sort last whichever way the column goes
            default => $query->orderByRaw('due_date IS NULL')->orderBy('due_date', $direction),
        };
    }

    /**
     * @param  LengthAwarePaginator<int, Task>  $tasks
     * @return array<int, array<string, mixed>>
     */
    private function present(LengthAwarePaginator $tasks): array
    {
        return collect($tasks->items())
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'key' => $task->project->key.'-'.$task->number,
                'title' => $task->title,
                'priority' => $task->priority->value,
                'priorityLabel' => $task->priority->label(),
                'priorityTone' => $task->priority->tone(),
                'priorityIcon' => $task->priority->icon(),
                'status' => $task->boardColumn->name,
                'statusTone' => $task->boardColumn->category->tone(),
                'isComplete' => $task->completed_at !== null,
                'dueDate' => $task->due_date?->toDateString(),
                'project' => [
                    'id' => $task->project->id,
                    'key' => $task->project->key,
                    'name' => $task->project->name,
                ],
                'assignee' => $task->assignee
                    ? ['id' => $task->assignee->id, 'name' => $task->assignee->name]
                    : null,
                'labels' => $task->labels->pluck('name')->all(),
                'comments' => $task->comments_count,
                'attachments' => $task->attachments_count,
            ])
            ->all();
    }

    /**
     * Counts for the scope tabs. One query per tab, but each is a covered
     * index read rather than a scan.
     *
     * @param  Builder<Project>  $visibleProjects
     * @return array<string, int>
     */
    private function counts(User $user, Builder $visibleProjects): array
    {
        $base = fn () => Task::query()->whereIn('project_id', $visibleProjects->clone());

        return [
            'all' => $base()->count(),
            'mine' => $base()->where('assignee_id', $user->id)->whereNull('completed_at')->count(),
            'overdue' => $base()->whereNull('completed_at')->whereDate('due_date', '<', today())->count(),
            'done' => $base()->whereNotNull('completed_at')->count(),
        ];
    }

    /**
     * One task, with everything the detail screen renders: the thread, the
     * checklist, the files and the audit trail.
     */
    public function show(Request $request, Task $task): Response
    {
        $this->authorize('view', $task->project);

        $task->load([
            // workspace_id is required by ProjectPolicy — a partial select must
            // still carry the keys the domain depends on
            'project:id,workspace_id,key,name,tasks_total,tasks_completed',
            'boardColumn:id,name,category',
            'assignee:id,name,job_title',
            'reporter:id,name,job_title',
            'labels:id,name,color',
            'watchers:id,name',
            'subtasks',
            'attachments.uploader:id,name',
            'allComments.author:id,name,job_title',
            'allComments.reactions',
        ]);

        return Inertia::render('tasks/show', [
            'task' => [
                'id' => $task->id,
                'key' => $task->key(),
                'title' => $task->title,
                'description' => $task->description,
                'priority' => $task->priority->value,
                'priorityLabel' => $task->priority->label(),
                'priorityTone' => $task->priority->tone(),
                'priorityIcon' => $task->priority->icon(),
                'status' => $task->boardColumn->name,
                'statusTone' => $task->boardColumn->category->tone(),
                'isComplete' => $task->completed_at !== null,
                'startDate' => $task->start_date?->toDateString(),
                'dueDate' => $task->due_date?->toDateString(),
                'estimateHours' => $task->estimate_hours,
                'createdAt' => $task->created_at?->toDayDateTimeString(),
                'updatedAt' => $task->updated_at?->diffForHumans(),
                'project' => [
                    'id' => $task->project->id,
                    'key' => $task->project->key,
                    'name' => $task->project->name,
                    'progress' => $task->project->progress(),
                ],
                'assignee' => $this->person($task->assignee),
                'reporter' => $this->person($task->reporter),
                'labels' => $task->labels
                    ->map(fn ($label) => ['id' => $label->id, 'name' => $label->name])
                    ->all(),
                'watchers' => $task->watchers
                    ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
                    ->all(),
                'isWatching' => $task->watchers->contains('id', $request->user()->id),
            ],
            'subtasks' => $task->subtasks
                ->map(fn (Subtask $subtask) => [
                    'id' => $subtask->id,
                    'title' => $subtask->title,
                    'isCompleted' => $subtask->is_completed,
                ])
                ->all(),
            'attachments' => $task->attachments
                ->map(fn (Attachment $file) => [
                    'id' => $file->id,
                    'name' => $file->original_name,
                    'size' => $file->humanSize(),
                    'isImage' => $file->isImage(),
                    'uploadedBy' => $file->uploader->name,
                    'uploadedAt' => $file->created_at?->toFormattedDateString(),
                ])
                ->all(),
            'comments' => $this->thread($task, $request->user()),
            'history' => $this->history($task),
            'canContribute' => $request->user()->can('contribute', $task->project),
        ]);
    }

    /**
     * @return array{id: int, name: string, title: string|null}|null
     */
    private function person(?User $user): ?array
    {
        return $user === null ? null : [
            'id' => $user->id,
            'name' => $user->name,
            'title' => $user->job_title,
        ];
    }

    /**
     * Top-level comments with their replies nested one level, which is as
     * deep as the thread goes.
     *
     * @return array<int, array<string, mixed>>
     */
    private function thread(Task $task, User $viewer): array
    {
        $all = $task->allComments->sortBy('id');

        return $all
            ->whereNull('parent_id')
            ->map(fn (Comment $comment) => $this->presentComment($comment, $viewer) + [
                'replies' => $all
                    ->where('parent_id', $comment->id)
                    ->map(fn (Comment $reply) => $this->presentComment($reply, $viewer))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentComment(Comment $comment, User $viewer): array
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'author' => $this->person($comment->author),
            'at' => $comment->created_at?->diffForHumans(),
            'isEdited' => $comment->isEdited(),
            'isMine' => $comment->user_id === $viewer->id,
            'reactions' => $comment->reactions->count(),
            'hasReacted' => $comment->reactions->contains('user_id', $viewer->id),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function history(Task $task): array
    {
        return Activity::query()
            ->where('task_id', $task->id)
            ->with('actor:id,name')
            ->latestFirst()
            ->limit(20)
            ->get()
            ->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'verb' => $activity->verb,
                'actor' => $activity->actor?->name,
                'meta' => isset($activity->properties['from'], $activity->properties['to'])
                    ? $activity->properties['from'].' → '.$activity->properties['to']
                    : null,
                'at' => $activity->created_at?->diffForHumans(),
            ])
            ->all();
    }

    public function create(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace();
        abort_if($workspace === null, 403);

        return Inertia::render('tasks/form', [
            'task' => null,
            'defaultProject' => $request->integer('project') ?: null,
            'options' => $this->formOptions($request),
        ]);
    }

    public function store(TaskRequest $request): RedirectResponse
    {
        $project = Project::whereKey($request->validated('project_id'))->firstOrFail();
        $this->authorize('contribute', $project);

        $task = DB::transaction(function () use ($request, $project) {
            $data = $request->validated();

            $column = BoardColumn::whereKey($data['board_column_id'])->firstOrFail();
            abort_unless($column->project_id === $project->id, 422);

            $task = Task::create([
                ...Arr::except($data, ['labels', 'subtasks']),
                'workspace_id' => $project->workspace_id,
                // Allocated under a row lock, never MAX(number)+1
                'number' => $project->allocateTaskNumber(),
                'reporter_id' => $request->user()->id,
                'created_by' => $request->user()->id,
                'position' => $this->nextPosition($column->id),
                'completed_at' => $column->category->isDone() ? now() : null,
            ]);

            $task->labels()->sync($data['labels'] ?? []);

            foreach (array_values($data['subtasks'] ?? []) as $index => $title) {
                $task->subtasks()->create(['title' => $title, 'position' => $index]);
            }

            $task->forceFill([
                'subtasks_total' => count($data['subtasks'] ?? []),
            ])->save();

            // The reporter and the assignee both follow the task by default
            $task->watchers()->syncWithoutDetaching(
                array_filter([$request->user()->id, $data['assignee_id'] ?? null])
            );

            $project->recountTasks();

            Activity::log(
                workspaceId: $project->workspace_id,
                verb: 'task.created',
                actorId: $request->user()->id,
                projectId: $project->id,
                taskId: $task->id,
                subject: $task,
            );

            return $task;
        });

        return Flash::success(
            to_route('tasks.show', $task),
            $task->key().' created.',
        );
    }

    public function edit(Request $request, Task $task): Response
    {
        $this->authorize('contribute', $task->project);

        $task->load(['labels:id', 'subtasks']);

        return Inertia::render('tasks/form', [
            'task' => [
                'id' => $task->id,
                'key' => $task->key(),
                'project_id' => $task->project_id,
                'board_column_id' => $task->board_column_id,
                'title' => $task->title,
                'description' => $task->description,
                'priority' => $task->priority->value,
                'assignee_id' => $task->assignee_id,
                'start_date' => $task->start_date?->toDateString(),
                'due_date' => $task->due_date?->toDateString(),
                'estimate_hours' => $task->estimate_hours,
                'labels' => $task->labels->pluck('id')->all(),
                'subtasks' => $task->subtasks->pluck('title')->all(),
            ],
            'defaultProject' => $task->project_id,
            'options' => $this->formOptions($request),
        ]);
    }

    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('contribute', $task->project);

        DB::transaction(function () use ($request, $task) {
            $data = $request->validated();

            $column = BoardColumn::whereKey($data['board_column_id'])->firstOrFail();
            abort_unless($column->project_id === $task->project_id, 422);

            $task->update([
                ...Arr::except($data, ['labels', 'subtasks', 'project_id']),
                'completed_at' => $column->category->isDone()
                    ? ($task->completed_at ?? now())
                    : null,
            ]);

            $task->labels()->sync($data['labels'] ?? []);
            $task->project->recountTasks();
        });

        return Flash::success(
            to_route('tasks.show', $task),
            'Task updated.',
        );
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('contribute', $task->project);

        $project = $task->project;
        $key = $task->key();

        DB::transaction(function () use ($task, $project) {
            $task->delete();
            $project->recountTasks();
        });

        return Flash::success(
            to_route('tasks.index'),
            "{$key} was deleted.",
        );
    }

    /** Append to the end of a column, leaving a gap for later inserts. */
    private function nextPosition(int $columnId): string
    {
        $last = (float) Task::query()
            ->where('board_column_id', $columnId)
            ->max('position');

        return (string) ($last + Task::POSITION_GAP);
    }

    /**
     * Every project the viewer can post into, each with its own columns —
     * changing the project in the form has to change the column list too.
     *
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace();

        return [
            'projects' => Project::query()
                ->visibleTo($user)
                ->with('boardColumns:id,project_id,name,category')
                ->orderBy('name')
                ->get(['id', 'key', 'name'])
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'key' => $project->key,
                    'name' => $project->name,
                    'columns' => $project->boardColumns
                        ->map(fn (BoardColumn $column) => [
                            'id' => $column->id,
                            'name' => $column->name,
                        ])
                        ->all(),
                ])
                ->all(),
            'people' => $workspace === null ? [] : $workspace->users()
                ->wherePivot('status', MemberStatus::Active->value)
                ->get(['users.id', 'users.name'])
                ->map(fn (User $person) => ['id' => $person->id, 'name' => $person->name])
                ->all(),
            'labels' => $workspace === null ? [] : $workspace->labels()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($label) => ['id' => $label->id, 'name' => $label->name])
                ->all(),
            'priorities' => collect(Priority::cases())
                ->map(fn (Priority $priority) => [
                    'value' => $priority->value,
                    'label' => $priority->label(),
                ])
                ->all(),
        ];
    }
}
