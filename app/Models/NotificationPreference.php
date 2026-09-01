<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per user per event — the matrix on the Notifications settings
 * screen. A row per event rather than a JSON blob, so adding a channel is a
 * migration instead of a data rewrite.
 *
 * @property int $id
 * @property int $user_id
 * @property string $event
 * @property bool $in_app
 * @property bool $email
 * @property bool $push
 */
#[Fillable(['user_id', 'event', 'in_app', 'email', 'push'])]
class NotificationPreference extends Model
{
    /** The events the settings screen lists, with their shipped defaults. */
    public const DEFAULTS = [
        'task_assigned' => ['in_app' => true, 'email' => true, 'push' => true],
        'mentioned' => ['in_app' => true, 'email' => true, 'push' => true],
        'comment_on_your_task' => ['in_app' => true, 'email' => false, 'push' => true],
        'task_status_changed' => ['in_app' => true, 'email' => false, 'push' => false],
        'project_deadline_near' => ['in_app' => true, 'email' => true, 'push' => false],
        'member_joined_project' => ['in_app' => false, 'email' => false, 'push' => false],
        'weekly_summary' => ['in_app' => false, 'email' => true, 'push' => false],
    ];

    protected function casts(): array
    {
        return [
            'in_app' => 'boolean',
            'email' => 'boolean',
            'push' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return match ($this->event) {
            'task_assigned' => "You're assigned a task",
            'mentioned' => 'Someone mentions you',
            'comment_on_your_task' => 'Comments on your tasks',
            'task_status_changed' => 'Task status changes',
            'project_deadline_near' => 'Project deadline approaching',
            'member_joined_project' => 'Someone joins a project',
            'weekly_summary' => 'Weekly summary',
            default => str_replace('_', ' ', ucfirst($this->event)),
        };
    }
}
