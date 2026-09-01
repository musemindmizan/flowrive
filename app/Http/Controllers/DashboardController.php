<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The dashboard is the heaviest read in the product — six panels, each a
 * different question about the same workspace. Every query below is written
 * against an index declared in the schema, and counts come from the counter
 * caches rather than COUNT(*) per card.
 */
class DashboardController extends Controller
{
    /** How far ahead the deadline panel looks. */
    private const DEADLINE_WINDOW_DAYS = 14;

    /** Open-task counts at which a person's workload reads as busy, then heavy. */
    private const BUSY_LOAD = 12;

    private const HEAVY_LOAD = 20;

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace();

        if ($workspace === null) {
            return Inertia::render('dashboard', [
                'workspace' => null,
                'greeting' => $this->greeting($user),
            ]);
        }

        return Inertia::render('dashboard', [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
            ],
            'greeting' => $this->greeting($user),
            'stats' => $this->stats($workspace, $user),
            'projects' => $this->projects($workspace),
            'myTasks' => $this->myTasks($user),
            'deadlines' => $this->deadlines($workspace),
            'activity' => $this->activity($workspace),
            'workload' => $this->workload($workspace),
        ]);
    }

    /* ------------------------------------------------------------ greeting */

    /**
     * @return array{name: string, part: string, message: string}
     */
    private function greeting(User $user): array
    {
        $hour = (int) Carbon::now($user->timezone ?: config('app.timezone'))->format('G');

        $part = match (true) {
            $hour < 12 => 'morning',
            $hour < 18 => 'afternoon',
            default => 'evening',
        };

        $dueThisWeek = Task::query()
            ->where('assignee_id', $user->id)
            ->whereNull('completed_at')
            ->whereBetween('due_date', [today(), today()->addWeek()])
            ->count();

        $nearDeadline = Project::query()
            ->whereHas('workspace.members', fn ($q) => $q->where('user_id', $user->id))
            ->whereNull('archived_at')
            ->whereBetween('deadline', [today(), today()->addDays(self::DEADLINE_WINDOW_DAYS)])
            ->count();

        return [
            'name' => explode(' ', $user->name)[0],
            'part' => $part,
            'message' => $this->greetingMessage($dueThisWeek, $nearDeadline),
        ];
    }

    private function greetingMessage(int $dueThisWeek, int $nearDeadline): string
    {
        if ($dueThisWeek === 0 && $nearDeadline === 0) {
            return 'Nothing is due in the next week. A good day to get ahead.';
        }

        $parts = [];

        if ($dueThisWeek > 0) {
            $parts[] = $dueThisWeek.' '.str('task')->plural($dueThisWeek).' due this week';
        }

        if ($nearDeadline > 0) {
            $parts[] = $nearDeadline.' '.str('project')->plural($nearDeadline).' approaching a deadline';
        }

        return 'You have '.implode(' and ', $parts).'.';
    }

    /* --------------------------------------------------------------- stats */

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stats(Workspace $workspace, User $user): array
    {
        // One grouped query rather than four counts
        $projects = Project::query()
            ->where('workspace_id', $workspace->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->toBase()
            ->pluck('total', 'status');

        $tasks = Task::query()
            ->where('workspace_id', $workspace->id)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(completed_at IS NULL) as open')
            ->selectRaw('SUM(completed_at IS NOT NULL) as done')
            ->selectRaw('SUM(completed_at >= ?) as done_this_week', [today()->subWeek()])
            ->toBase()
            ->first();

        $members = $workspace->members()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->toBase()
            ->pluck('total', 'status');

        $assignedToMe = Task::query()
            ->where('assignee_id', $user->id)
            ->whereNull('completed_at')
            ->count();

        $dueThisWeek = Task::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('completed_at')
            ->whereBetween('due_date', [today(), today()->addWeek()])
            ->count();

        return [
            [
                'key' => 'projects',
                'label' => 'Total projects',
                'value' => (int) $projects->sum(),
                'icon' => 'folder',
                'tone' => 'brand',
                'meta' => sprintf(
                    '%d active · %d on hold · %d completed',
                    (int) $projects->get('in_progress', 0) + (int) $projects->get('planning', 0) + (int) $projects->get('in_review', 0),
                    (int) $projects->get('on_hold', 0),
                    (int) $projects->get('completed', 0),
                ),
            ],
            [
                'key' => 'active-tasks',
                'label' => 'Active tasks',
                'value' => (int) $tasks->open,
                'icon' => 'square-check',
                'tone' => 'info',
                'badge' => $dueThisWeek > 0 ? $dueThisWeek.' due' : null,
                'badgeTone' => 'warning',
                'meta' => $assignedToMe.' assigned to you',
            ],
            [
                'key' => 'completed',
                'label' => 'Completed tasks',
                'value' => (int) $tasks->done,
                'icon' => 'circle-check-big',
                'tone' => 'success',
                'meta' => (int) $tasks->done_this_week.' completed this week',
            ],
            [
                'key' => 'members',
                'label' => 'Team members',
                'value' => (int) $members->sum(),
                'icon' => 'users',
                'tone' => 'warning',
                'badge' => $members->get('invited') ? $members->get('invited').' invited' : null,
                'badgeTone' => 'neutral',
            ],
        ];
    }

    /* ------------------------------------------------------------ projects */

    /**
     * @return array<int, array<string, mixed>>
     */
    private function projects(Workspace $workspace): array
    {
        return Project::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('archived_at')
            ->where('status', '!=', ProjectStatus::Completed)
            ->with(['members:id,name', 'lead:id,name'])
            ->orderByRaw('deadline IS NULL, deadline')
            ->limit(5)
            ->get()
            ->map(fn (Project $project) => [
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
                'members' => $this->people($project->members),
            ])
            ->all();
    }

    /* ------------------------------------------------------------ my tasks */

    /**
     * @return array<int, array<string, mixed>>
     */
    private function myTasks(User $user): array
    {
        return Task::query()
            ->where('assignee_id', $user->id)
            ->whereNull('completed_at')
            ->with(['project:id,key,name'])
            ->orderByRaw('due_date IS NULL, due_date')
            ->limit(5)
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'key' => $task->key(),
                'title' => $task->title,
                'priority' => $task->priority->value,
                'priorityLabel' => $task->priority->label(),
                'priorityTone' => $task->priority->tone(),
                'priorityIcon' => $task->priority->icon(),
                'dueDate' => $task->due_date?->toDateString(),
                'project' => [
                    'id' => $task->project->id,
                    'name' => $task->project->name,
                ],
            ])
            ->all();
    }

    /* ----------------------------------------------------------- deadlines */

    /**
     * Tasks and project deadlines land in one list, sorted together — the
     * question the panel answers is "what is next", not "what kind of thing".
     *
     * @return array<int, array<string, mixed>>
     */
    private function deadlines(Workspace $workspace): array
    {
        $window = today()->addDays(self::DEADLINE_WINDOW_DAYS);

        $tasks = Task::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->where('due_date', '<=', $window)
            ->with('project:id,name')
            ->orderBy('due_date')
            ->limit(5)
            ->get()
            ->map(fn (Task $task) => [
                'type' => 'task',
                'id' => 'task-'.$task->id,
                'title' => $task->title,
                'meta' => $task->project->name,
                'date' => $task->due_date->toDateString(),
            ]);

        $projects = Project::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('archived_at')
            ->where('status', '!=', ProjectStatus::Completed)
            ->whereNotNull('deadline')
            ->whereBetween('deadline', [today(), $window])
            ->orderBy('deadline')
            ->limit(3)
            ->get()
            ->map(fn (Project $project) => [
                'type' => 'project',
                'id' => 'project-'.$project->id,
                'title' => $project->name,
                'meta' => 'Project deadline',
                'date' => $project->deadline->toDateString(),
            ]);

        return $tasks->concat($projects)
            ->sortBy('date')
            ->take(5)
            ->values()
            ->all();
    }

    /* ------------------------------------------------------------ activity */

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activity(Workspace $workspace): array
    {
        return Activity::query()
            ->where('workspace_id', $workspace->id)
            ->with(['actor:id,name', 'project:id,name', 'task:id,title'])
            ->latestFirst()
            ->limit(7)
            ->get()
            ->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'verb' => $activity->verb,
                'actor' => $activity->actor
                    ? ['id' => $activity->actor->id, 'name' => $activity->actor->name]
                    : null,
                'target' => $activity->task->title ?? $activity->project->name ?? null,
                'project' => $activity->project?->name,
                'meta' => $this->activityMeta($activity),
                'at' => $activity->created_at?->diffForHumans(),
            ])
            ->all();
    }

    private function activityMeta(Activity $activity): ?string
    {
        $from = $activity->properties['from'] ?? null;
        $to = $activity->properties['to'] ?? null;

        return $from && $to ? $from.' → '.$to : null;
    }

    /* ------------------------------------------------------------ workload */

    /**
     * Open tasks per person, busiest first.
     *
     * The bar is each person's load relative to the busiest teammate, not a
     * share of some invented capacity — a flat "everyone can hold 14" made
     * every bar read 100% and told the reader nothing. The tone is what
     * flags trouble, and the open count is printed beside it so the warning
     * never rests on colour alone.
     *
     * @return array<int, array<string, mixed>>
     */
    private function workload(Workspace $workspace): array
    {
        $counts = Task::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('completed_at')
            ->whereNotNull('assignee_id')
            ->selectRaw('assignee_id, COUNT(*) as open_tasks')
            ->groupBy('assignee_id')
            ->orderByDesc('open_tasks')
            ->limit(5)
            ->toBase()
            ->pluck('open_tasks', 'assignee_id');

        if ($counts->isEmpty()) {
            return [];
        }

        $busiest = max(1, (int) $counts->max());

        $users = User::query()
            ->whereIn('id', $counts->keys())
            ->get(['id', 'name'])
            ->keyBy('id');

        return $counts
            ->map(function (int $open, int $userId) use ($users, $busiest) {
                $user = $users[$userId];

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'open' => $open,
                    'percent' => (int) round($open / $busiest * 100),
                    'tone' => match (true) {
                        $open >= self::HEAVY_LOAD => 'danger',
                        $open >= self::BUSY_LOAD => 'warning',
                        default => 'success',
                    },
                ];
            })
            ->values()
            ->all();
    }

    /* ------------------------------------------------------------- helpers */

    /**
     * @param  Collection<int, User>  $people
     * @return array<int, array{id: int, name: string}>
     */
    private function people(Collection $people): array
    {
        return $people
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
            ->values()
            ->all();
    }
}
