<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only activity log. Polymorphic subject, but workspace_id and
 * project_id are denormalised so the feed never joins just to filter.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int|null $project_id
 * @property int|null $actor_id
 * @property string $verb
 * @property array<string, mixed>|null $properties
 * @property Task|null $task
 * @property Project|null $project
 * @property User|null $actor
 */
#[Fillable([
    'workspace_id', 'project_id', 'task_id', 'actor_id', 'verb',
    'subject_type', 'subject_id', 'properties',
])]
class Activity extends Model
{
    /** Append-only: there is nothing to update. */
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Newest first. Ordering by id rather than created_at is deliberate — on
     * an append-only table the primary key is already chronological, so the
     * index stays narrow and the sort disappears.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('id');
    }

    /**
     * Record one entry in the feed.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function log(
        int $workspaceId,
        string $verb,
        ?int $actorId = null,
        ?int $projectId = null,
        ?int $taskId = null,
        ?Model $subject = null,
        array $properties = [],
    ): self {
        return self::create([
            'workspace_id' => $workspaceId,
            'project_id' => $projectId,
            'task_id' => $taskId,
            'actor_id' => $actorId,
            'verb' => $verb,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
        ]);
    }
}
