<?php

namespace App\Enums;

/**
 * The per-project permission layer. Independent of {@see WorkspaceRole}:
 * a workspace Manager is only a project Lead where a row says so.
 */
enum ProjectRole: string
{
    case Lead = 'lead';
    case Member = 'member';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Project lead',
            self::Member => 'Member',
            self::Viewer => 'Viewer',
        };
    }

    public function canEditTasks(): bool
    {
        return $this !== self::Viewer;
    }

    public function canManageProject(): bool
    {
        return $this === self::Lead;
    }
}
