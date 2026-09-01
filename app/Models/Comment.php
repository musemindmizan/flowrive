<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A comment on a task, optionally a reply to another comment.
 *
 * @property int $id
 * @property int $task_id
 * @property int $user_id
 * @property int|null $parent_id
 * @property string $body
 * @property CarbonImmutable|null $edited_at
 */
#[Fillable(['task_id', 'user_id', 'parent_id', 'body'])]
class Comment extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['edited_at' => 'datetime'];
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Comment, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Comment, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<CommentReaction, $this> */
    public function reactions(): HasMany
    {
        return $this->hasMany(CommentReaction::class);
    }

    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }
}
