<?php

namespace App\Models;

use App\Enums\Priority;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The unit of work.
 *
 * Status is the board column it sits in — there is deliberately no status
 * enum to drift away from it.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $project_id
 * @property int $board_column_id
 * @property int $number
 * @property string $title
 * @property string|null $description
 * @property Priority $priority
 * @property int|null $assignee_id
 * @property CarbonImmutable|null $start_date
 * @property CarbonImmutable|null $due_date
 * @property string|null $estimate_hours
 * @property string $position
 * @property CarbonImmutable|null $completed_at
 */
#[Fillable([
    'workspace_id', 'project_id', 'board_column_id', 'number', 'title',
    'description', 'priority', 'assignee_id', 'reporter_id', 'created_by',
    'start_date', 'due_date', 'estimate_hours', 'position', 'completed_at',
])]
class Task extends Model
{
    use SoftDeletes;

    /** Gap used when appending to a column; halved on each insert between rows. */
    public const POSITION_GAP = 1000;

    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'estimate_hours' => 'decimal:2',
            'position' => 'decimal:10',
            'number' => 'integer',
        ];
    }

    /* ---------------------------------------------------------- relations */

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

    /** @return BelongsTo<BoardColumn, $this> */
    public function boardColumn(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** @return HasMany<Subtask, $this> */
    public function subtasks(): HasMany
    {
        return $this->hasMany(Subtask::class)->orderBy('position');
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id');
    }

    /** @return HasMany<Comment, $this> */
    public function allComments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** @return HasMany<Attachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /** @return BelongsToMany<Label, $this> */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_watchers')
            ->withTimestamps();
    }

    /* ------------------------------------------------------------ scopes */

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNull('completed_at')->whereDate('due_date', '<', today());
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assignee_id', $userId);
    }

    /**
     * Uses the FULLTEXT index; always scope by workspace first.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->whereFullText(['title', 'description'], $term);
    }

    /* ----------------------------------------------------------- helpers */

    /** The display key shown throughout the UI, e.g. WEB-118. */
    public function key(): string
    {
        return $this->project->key.'-'.$this->number;
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isComplete()
            && $this->due_date !== null
            && $this->due_date->isPast();
    }

    /**
     * Rank for dropping between two cards. A fractional position means a drag
     * writes exactly one row instead of renumbering the whole column.
     */
    public static function positionBetween(?string $before, ?string $after): string
    {
        if ($before === null && $after === null) {
            return (string) self::POSITION_GAP;
        }

        if ($before === null) {
            return (string) ((float) $after / 2);
        }

        if ($after === null) {
            return (string) ((float) $before + self::POSITION_GAP);
        }

        return (string) (((float) $before + (float) $after) / 2);
    }
}
