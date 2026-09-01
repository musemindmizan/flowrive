<?php

namespace App\Models;

use App\Enums\ColumnCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A workflow stage on the board. The name is editable; the category is not,
 * so reports and "is it done?" survive a rename.
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property ColumnCategory $category
 * @property int $position
 * @property int|null $wip_limit
 */
#[Fillable(['project_id', 'name', 'category', 'position', 'wip_limit'])]
class BoardColumn extends Model
{
    protected function casts(): array
    {
        return [
            'category' => ColumnCategory::class,
            'position' => 'integer',
            'wip_limit' => 'integer',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('position');
    }

    public function isDone(): bool
    {
        return $this->category->isDone();
    }

    public function isOverWipLimit(): bool
    {
        return $this->wip_limit !== null && $this->tasks()->count() > $this->wip_limit;
    }
}
