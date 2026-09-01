<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per person per emoji, so the count is a COUNT and "did I react"
 * is an existence check.
 *
 * @property int $comment_id
 * @property int $user_id
 * @property string $emoji
 */
#[Fillable(['comment_id', 'user_id', 'emoji'])]
class CommentReaction extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    /** @return BelongsTo<Comment, $this> */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
