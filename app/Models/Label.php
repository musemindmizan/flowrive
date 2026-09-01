<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A workspace-wide tag. A lookup table rather than an enum because members
 * create and rename them.
 *
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property string $color
 */
#[Fillable(['workspace_id', 'name', 'color'])]
class Label extends Model
{
    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsToMany<Task, $this> */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class);
    }
}
