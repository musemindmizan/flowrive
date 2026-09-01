<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;

/**
 * Authorisation follows the two membership layers: the workspace role decides
 * what you may do to the platform, the project role what you may do inside
 * one project. An Admin needs no project_members row; everyone else does.
 */
class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return Project::query()
            ->visibleTo($user)
            ->whereKey($project->getKey())
            ->exists();
    }

    public function update(User $user, Project $project): bool
    {
        if ($this->isWorkspaceAdmin($user, $project)) {
            return true;
        }

        return $this->projectRole($user, $project) === ProjectRole::Lead;
    }

    /** Moving cards and editing tasks — anything but a Viewer. */
    public function contribute(User $user, Project $project): bool
    {
        if ($this->isWorkspaceAdmin($user, $project)) {
            return true;
        }

        return $this->projectRole($user, $project)?->canEditTasks() ?? false;
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->isWorkspaceAdmin($user, $project);
    }

    private function isWorkspaceAdmin(User $user, Project $project): bool
    {
        return $user->roleIn($project->workspace_id)?->seesAllProjects() ?? false;
    }

    private function projectRole(User $user, Project $project): ?ProjectRole
    {
        $member = $project->members->firstWhere('id', $user->id)
            ?? $project->members()->find($user->id);

        $role = $member?->getAttribute('pivot')?->role;

        return $role === null ? null : ProjectRole::from($role);
    }
}
