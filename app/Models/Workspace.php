<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The tenant root.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $plan
 * @property int $seat_limit
 * @property int $storage_limit_bytes
 * @property WorkspaceRole $default_member_role
 * @property int $owner_id
 * @property array<string, mixed>|null $settings
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable([
    'name', 'slug', 'plan', 'seat_limit', 'storage_limit_bytes',
    'default_member_role', 'owner_id', 'settings',
])]
class Workspace extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'default_member_role' => WorkspaceRole::class,
            'settings' => 'array',
            'seat_limit' => 'integer',
            'storage_limit_bytes' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<WorkspaceMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    /**
     * Everyone with a seat, with their role and status on the pivot.
     *
     * @return BelongsToMany<User, $this, WorkspaceMember>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->using(WorkspaceMember::class)
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return HasMany<Label, $this> */
    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }

    /** @return HasMany<Invitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
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

    /** Bytes stored — one indexed SUM, which is why attachments carry workspace_id. */
    public function storageUsed(): int
    {
        return (int) $this->attachments()->sum('size_bytes');
    }

    public function seatsUsed(): int
    {
        return $this->members()->count();
    }
}
