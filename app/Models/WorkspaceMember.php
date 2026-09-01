<?php

namespace App\Models;

use App\Enums\MemberStatus;
use App\Enums\WorkspaceRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A seat. Doubles as the pivot for Workspace::users().
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $user_id
 * @property WorkspaceRole $role
 * @property MemberStatus $status
 * @property int|null $invited_by
 * @property CarbonImmutable|null $joined_at
 */
#[Fillable(['workspace_id', 'user_id', 'role', 'status', 'invited_by', 'joined_at'])]
class WorkspaceMember extends Pivot
{
    protected $table = 'workspace_members';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
            'status' => MemberStatus::class,
            'joined_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
