<?php

namespace App\Enums;

/**
 * The tabs on the notification centre.
 */
enum NotificationCategory: string
{
    case Mention = 'mention';
    case Task = 'task';
    case Project = 'project';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Mention => 'Mentions',
            self::Task => 'Tasks',
            self::Project => 'Projects',
            self::System => 'System',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Mention => 'at-sign',
            self::Task => 'square-check',
            self::Project => 'folder',
            self::System => 'settings',
        };
    }
}
