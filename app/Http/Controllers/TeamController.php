<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The workspace roster. Role and status come from the membership row, not the
 * user, so the same person can be Active here and Invited elsewhere.
 */
class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace();
        abort_if($workspace === null, 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
        ]);

        $search = $filters['search'] ?? '';

        $members = $workspace->members()
            ->with('user:id,name,email,job_title,last_seen_at,created_at')
            ->when($search !== '', fn ($query) => $query->whereHas(
                'user',
                fn ($user) => $user
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('job_title', 'like', "%{$search}%")
            ))
            ->when(
                $filters['role'] ?? null,
                fn ($query, string $role) => $query->where('role', $role)
            )
            ->when(
                $filters['status'] ?? null,
                fn ($query, string $status) => $query->where('status', $status)
            )
            ->get();

        // Two grouped queries rather than a pair per person
        $projectCounts = $this->projectCountsByUser($workspace);
        $taskCounts = $this->openTaskCountsByUser($workspace);

        return Inertia::render('team/index', [
            'members' => $members
                ->map(fn (WorkspaceMember $member) => [
                    'id' => $member->user->id,
                    'name' => $member->user->name,
                    'email' => $member->user->email,
                    'title' => $member->user->job_title,
                    'role' => $member->role->value,
                    'roleLabel' => $member->role->label(),
                    'status' => $member->status->value,
                    'statusLabel' => $member->status->label(),
                    'statusTone' => $member->status->tone(),
                    'presence' => $member->user->presence(),
                    'joined' => $member->joined_at?->toFormattedDateString(),
                    'projects' => $projectCounts[$member->user->id] ?? 0,
                    'openTasks' => $taskCounts[$member->user->id] ?? 0,
                    'isYou' => $member->user->id === $request->user()->id,
                ])
                ->values()
                ->all(),
            'filters' => [
                'search' => $search,
                'role' => $filters['role'] ?? null,
                'status' => $filters['status'] ?? null,
            ],
            'roleCounts' => $workspace->members()
                ->selectRaw('role, COUNT(*) as total')
                ->groupBy('role')
                ->toBase()
                ->pluck('total', 'role')
                ->all(),
            'invitations' => Invitation::query()
                ->where('workspace_id', $workspace->id)
                ->pending()
                ->with('inviter:id,name')
                ->get()
                ->map(fn (Invitation $invitation) => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role->label(),
                    'invitedBy' => $invitation->inviter->name,
                    'sentAt' => $invitation->created_at?->diffForHumans(),
                    'expiresAt' => $invitation->expires_at->diffForHumans(),
                ])
                ->all(),
            'roleOptions' => collect(WorkspaceRole::cases())
                ->map(fn (WorkspaceRole $role) => [
                    'value' => $role->value,
                    'label' => $role->label(),
                    'description' => $role->description(),
                ])
                ->all(),
            'canInvite' => $request->user()->roleIn($workspace)?->canCreateProjects() ?? false,
        ]);
    }

    public function show(Request $request, User $member): Response
    {
        $workspace = $request->user()->currentWorkspace();
        abort_if($workspace === null, 403);

        $membership = $workspace->members()->where('user_id', $member->id)->first();
        abort_if($membership === null, 404);

        return Inertia::render('team/show', [
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'title' => $member->job_title,
                'phone' => $member->phone,
                'bio' => $member->bio,
                'department' => $member->department,
                'timezone' => $member->timezone,
                'presence' => $member->presence(),
                'role' => $membership->role->label(),
                'status' => $membership->status->value,
                'statusLabel' => $membership->status->label(),
                'statusTone' => $membership->status->tone(),
                'joined' => $membership->joined_at?->toFormattedDateString(),
            ],
            'stats' => [
                'openTasks' => Task::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('assignee_id', $member->id)
                    ->whereNull('completed_at')
                    ->count(),
                'completed' => Task::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('assignee_id', $member->id)
                    ->whereNotNull('completed_at')
                    ->count(),
                'projects' => $this->projectCountsByUser($workspace)[$member->id] ?? 0,
                'overdue' => Task::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('assignee_id', $member->id)
                    ->whereNull('completed_at')
                    ->whereDate('due_date', '<', today())
                    ->count(),
            ],
            'projects' => Project::query()
                ->visibleTo($request->user())
                ->whereHas('members', fn ($query) => $query->where('users.id', $member->id))
                ->orderByRaw('deadline IS NULL, deadline')
                ->get()
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'key' => $project->key,
                    'name' => $project->name,
                    'progress' => $project->progress(),
                    'statusLabel' => $project->status->label(),
                    'statusTone' => $project->status->tone(),
                ])
                ->all(),
            'tasks' => Task::query()
                ->whereIn('project_id', Project::query()->visibleTo($request->user())->select('id'))
                ->where('assignee_id', $member->id)
                ->whereNull('completed_at')
                ->with(['project:id,key,name', 'boardColumn:id,name,category'])
                ->orderByRaw('due_date IS NULL, due_date')
                ->limit(10)
                ->get()
                ->map(fn (Task $task) => [
                    'id' => $task->id,
                    'key' => $task->project->key.'-'.$task->number,
                    'title' => $task->title,
                    'status' => $task->boardColumn->name,
                    'statusTone' => $task->boardColumn->category->tone(),
                    'priorityLabel' => $task->priority->label(),
                    'priorityTone' => $task->priority->tone(),
                    'priorityIcon' => $task->priority->icon(),
                    'dueDate' => $task->due_date?->toDateString(),
                    'project' => $task->project->name,
                ])
                ->all(),
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function projectCountsByUser(Workspace $workspace): array
    {
        return DB::table('project_members')
            ->join('projects', 'projects.id', '=', 'project_members.project_id')
            ->where('projects.workspace_id', $workspace->id)
            ->whereNull('projects.deleted_at')
            ->selectRaw('project_members.user_id, COUNT(*) as total')
            ->groupBy('project_members.user_id')
            ->pluck('total', 'user_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function openTaskCountsByUser(Workspace $workspace): array
    {
        return Task::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('completed_at')
            ->whereNotNull('assignee_id')
            ->selectRaw('assignee_id, COUNT(*) as total')
            ->groupBy('assignee_id')
            ->toBase()
            ->pluck('total', 'assignee_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }
}
