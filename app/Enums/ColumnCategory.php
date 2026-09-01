<?php

namespace App\Enums;

/**
 * A board column's display name is editable; this category is not. Reports and
 * "is it finished?" checks group by the category, so renaming a column never
 * breaks them.
 */
enum ColumnCategory: string
{
    case Backlog = 'backlog';
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Review = 'review';
    case Done = 'done';

    public function defaultName(): string
    {
        return match ($this) {
            self::Backlog => 'Backlog',
            self::Todo => 'To Do',
            self::InProgress => 'In Progress',
            self::Review => 'Review',
            self::Done => 'Done',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Backlog => 'neutral',
            self::Todo => 'info',
            self::InProgress => 'brand',
            self::Review => 'warning',
            self::Done => 'success',
        };
    }

    public function isDone(): bool
    {
        return $this === self::Done;
    }

    /**
     * The five columns every new project starts with, in board order.
     *
     * @return array<int, self>
     */
    public static function defaults(): array
    {
        return [self::Backlog, self::Todo, self::InProgress, self::Review, self::Done];
    }
}
