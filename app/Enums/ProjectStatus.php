<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Planning = 'planning';
    case InProgress = 'in_progress';
    case InReview = 'in_review';
    case OnHold = 'on_hold';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Planning => 'Planning',
            self::InProgress => 'In Progress',
            self::InReview => 'In Review',
            self::OnHold => 'On Hold',
            self::Completed => 'Completed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Planning => 'neutral',
            self::InProgress => 'info',
            self::InReview => 'warning',
            self::OnHold => 'neutral',
            self::Completed => 'success',
        };
    }

    public function isOpen(): bool
    {
        return $this !== self::Completed;
    }
}
