<?php

namespace App\Enums;

/**
 * The platform permission layer: what you may do to the workspace itself.
 * The per-project layer is {@see ProjectRole}.
 */
enum WorkspaceRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::Member => 'Member',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Manages billing, workspace settings, every project and every member.',
            self::Manager => 'Creates projects, manages members and assigns tasks.',
            self::Member => 'Works on assigned tasks, comments and uploads files.',
        };
    }

    /** Admins reach every project without needing a project_members row. */
    public function seesAllProjects(): bool
    {
        return $this === self::Admin;
    }

    public function canCreateProjects(): bool
    {
        return $this !== self::Member;
    }
}
