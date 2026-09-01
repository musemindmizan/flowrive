<?php

namespace App\Models;

use App\Enums\ColumnCategory;
use App\Enums\Priority;
use App\Enums\ProjectStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Aggregate root: groups tasks, files and conversation.
 *
 * @property int $id
 * @property int $workspace_id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property ProjectStatus $status
 * @property Priority $priority
 * @property CarbonImmutable|null $start_date
 * @property CarbonImmutable|null $deadline
 * @property int $next_task_number
 * @property int $tasks_total
 * @property int $tasks_completed
 */
#[Fillable([
    'workspace_id', 'key', 'name', 'description', 'status', 'priority',
    'visibility', 'color', 'cover_path', 'start_date', 'deadline',
    'lead_id', 'created_by',
])]
class Project extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'priority' => Priority::class,
            'start_date' => 'date',
            'deadline' => 'date',
            'archived_at' => 'datetime',
            'next_task_number' => 'integer',
            'tasks_total' => 'integer',
            'tasks_completed' => 'integer',
        ];
    }

    /* ---------------------------------------------------------- relations */

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<User, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot(['role', 'added_by'])
            ->withTimestamps();
    }

    /** @return HasMany<BoardColumn, $this> */
    public function boardColumns(): HasMany
    {
        return $this->hasMany(BoardColumn::class)->orderBy('position');
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return HasMany<Attachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /** @return HasMany<Activity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_favorites')
            ->withPivot('position');
    }

    /* ------------------------------------------------------------ scopes */

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForWorkspace(Builder $query, int $workspaceId): Builder
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /**
     * Projects this person is allowed to open.
     *
     * Three ways in, matching the two-layer role model: workspace Admins see
     * everything, a project marked `workspace` visibility is open to anyone
     * with a seat, and everyone else needs a project_members row.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $workspace = $user->currentWorkspace();

        if ($workspace === null) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('workspace_id', $workspace->id);

        if ($user->roleIn($workspace)?->seesAllProjects()) {
            return $query;
        }

        return $query->where(function (Builder $scoped) use ($user) {
            $scoped
                ->where('visibility', 'workspace')
                ->orWhereHas('members', fn (Builder $m) => $m->where('users.id', $user->id));
        });
    }

    /* ----------------------------------------------------------- helpers */

    /** Progress is computed from the two counter caches, never stored. */
    public function progress(): int
    {
        if ($this->tasks_total === 0) {
            return 0;
        }

        return (int) round($this->tasks_completed / $this->tasks_total * 100);
    }

    /**
     * Allocate the next task number atomically.
     *
     * MAX(number)+1 races under concurrent creates and hands two people the
     * same key, so the counter is incremented under a row lock instead. Call
     * this inside the same transaction as the task insert.
     */
    public function allocateTaskNumber(): int
    {
        return DB::transaction(function (): int {
            $number = (int) DB::table('projects')
                ->where('id', $this->id)
                ->lockForUpdate()
                ->value('next_task_number');

            DB::table('projects')
                ->where('id', $this->id)
                ->update(['next_task_number' => $number + 1]);

            $this->next_task_number = $number + 1;

            return $number;
        });
    }

    /** Every new project starts with the five default board columns. */
    public function createDefaultColumns(): void
    {
        foreach (ColumnCategory::defaults() as $index => $category) {
            $this->boardColumns()->create([
                'name' => $category->defaultName(),
                'category' => $category,
                'position' => $index,
            ]);
        }
    }

    /** Recompute the counter caches from source — the nightly reconcile job. */
    public function recountTasks(): void
    {
        $this->forceFill([
            'tasks_total' => $this->tasks()->count(),
            'tasks_completed' => $this->tasks()->whereNotNull('completed_at')->count(),
        ])->save();
    }
}
