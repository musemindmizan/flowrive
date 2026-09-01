<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Http\Requests\ProjectRequest;
use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The project list. Filtering, sorting and searching all happen in SQL rather
 * than in the browser, so the page stays correct once a workspace holds more
 * projects than fit in one payload.
 */
class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'array'],
            'status.*' => ['string'],
            'sort' => ['nullable', 'in:deadline,name,progress,priority'],
        ]);

        $sort = $filters['sort'] ?? 'deadline';
        $statuses = $filters['status'] ?? [];
        $search = $filters['search'] ?? '';

        $projects = Project::query()
            ->visibleTo($request->user())
            ->whereNull('archived_at')
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('key', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
            ))
            ->when($statuses !== [], fn ($query) => $query->whereIn('status', $statuses))
            ->with(['members:id,name', 'lead:id,name'])
            ->withCount('attachments')
            ->tap(fn ($query) => $this->applySort($query, $sort))
            ->get()
            ->map(fn (Project $project) => $this->present($project))
            ->all();

        return Inertia::render('projects/index', [
            'projects' => $projects,
            'filters' => [
                'search' => $search,
                'status' => $statuses,
                'sort' => $sort,
            ],
            'statusOptions' => collect(ProjectStatus::cases())
                ->map(fn (ProjectStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->all(),
            'summary' => $this->summary($request->user()),
        ]);
    }

    /**
     * @param  Builder<Project>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'name' => $query->orderBy('name'),
            // Progress is derived, so it is ordered by the ratio the two
            // counter caches describe rather than by a stored column.
            'progress' => $query->orderByRaw(
                'CASE WHEN tasks_total = 0 THEN 0 ELSE tasks_completed / tasks_total END DESC'
            ),
            'priority' => $query->orderByRaw(
                "FIELD(priority, 'critical', 'high', 'medium', 'low')"
            ),
            default => $query->orderByRaw('deadline IS NULL, deadline'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Project $project): array
    {
        return [
            'id' => $project->id,
            'key' => $project->key,
            'name' => $project->name,
            'description' => $project->description,
            'status' => $project->status->value,
            'statusLabel' => $project->status->label(),
            'statusTone' => $project->status->tone(),
            'priority' => $project->priority->value,
            'priorityLabel' => $project->priority->label(),
            'priorityTone' => $project->priority->tone(),
            'priorityIcon' => $project->priority->icon(),
            'progress' => $project->progress(),
            'tasksTotal' => $project->tasks_total,
            'tasksCompleted' => $project->tasks_completed,
            'files' => $project->attachments_count,
            'deadline' => $project->deadline?->toDateString(),
            'lead' => $project->lead
                ? ['id' => $project->lead->id, 'name' => $project->lead->name]
                : null,
            'members' => $project->members
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{projects: int, active: int, tasks: int}
     */
    private function summary(User $user): array
    {
        /** @var Collection<int, Project> $all */
        $all = Project::query()
            ->visibleTo($user)
            ->whereNull('archived_at')
            ->get(['id', 'status', 'tasks_total']);

        return [
            'projects' => $all->count(),
            'active' => $all->filter(fn (Project $p) => $p->status->isOpen())->count(),
            'tasks' => (int) $all->sum('tasks_total'),
        ];
    }

    public function create(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace();
        abort_if($workspace === null, 403);

        return Inertia::render('projects/form', [
            'project' => null,
            'options' => $this->formOptions($workspace),
        ]);
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace();
        abort_if($workspace === null, 403);

        $project = DB::transaction(function () use ($request, $workspace) {
            $data = $request->validated();

            $project = $workspace->projects()->create([
                ...Arr::except($data, ['members']),
                'created_by' => $request->user()->id,
                'lead_id' => $data['lead_id'] ?? $request->user()->id,
            ]);

            // Every project starts with the five default columns; the board is
            // useless without them and creating one without is not a state the
            // product should ever be in.
            $project->createDefaultColumns();

            foreach ($this->memberIds($data, $project) as $memberId) {
                $project->members()->attach($memberId, [
                    'role' => $memberId === $project->lead_id ? 'lead' : 'member',
                    'added_by' => $request->user()->id,
                ]);
            }

            Activity::log(
                workspaceId: $workspace->id,
                verb: 'project.created',
                actorId: $request->user()->id,
                projectId: $project->id,
                subject: $project,
            );

            return $project;
        });

        return to_route('projects.board', $project)
            ->with('success', "{$project->name} is ready for your team.");
    }

    public function edit(Request $request, Project $project): Response
    {
        $this->authorize('update', $project);

        $project->load('members:id,name');

        return Inertia::render('projects/form', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'key' => $project->key,
                'description' => $project->description,
                'status' => $project->status->value,
                'priority' => $project->priority->value,
                'visibility' => $project->visibility,
                'color' => $project->color,
                'start_date' => $project->start_date?->toDateString(),
                'deadline' => $project->deadline?->toDateString(),
                'lead_id' => $project->lead_id,
                'members' => $project->members->pluck('id')->all(),
                'tasksTotal' => $project->tasks_total,
            ],
            'options' => $this->formOptions($project->workspace),
        ]);
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        DB::transaction(function () use ($request, $project) {
            $data = $request->validated();

            $project->update(Arr::except($data, ['members']));

            $sync = [];
            foreach ($this->memberIds($data, $project) as $memberId) {
                $sync[$memberId] = [
                    'role' => $memberId === $project->lead_id ? 'lead' : 'member',
                ];
            }

            $project->members()->sync($sync);
        });

        return to_route('projects.board', $project)
            ->with('success', 'Project updated.');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $name = $project->name;
        $project->delete();

        return to_route('projects.index')
            ->with('success', "{$name} was deleted.");
    }

    /**
     * The chosen members plus the lead, who is always one.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    private function memberIds(array $data, Project $project): array
    {
        $chosen = array_map('intval', Arr::wrap($data['members'] ?? []));
        $chosen[] = (int) $project->lead_id;

        return array_values(array_unique($chosen));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Workspace $workspace): array
    {
        return [
            'people' => $workspace->users()
                ->wherePivot('status', MemberStatus::Active->value)
                ->get(['users.id', 'users.name', 'users.job_title'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'title' => $user->job_title,
                ])
                ->all(),
            'statuses' => collect(ProjectStatus::cases())
                ->map(fn (ProjectStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
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
