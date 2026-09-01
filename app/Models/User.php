<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\MemberStatus;
use App\Enums\WorkspaceRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $avatar_path
 * @property string|null $job_title
 * @property string|null $phone
 * @property string|null $bio
 * @property string|null $department
 * @property string $timezone
 * @property string $locale
 * @property int $week_starts_on
 * @property string $digest_frequency
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'name', 'email', 'password', 'avatar_path', 'job_title', 'phone', 'bio',
    'department', 'timezone', 'locale', 'date_format', 'week_starts_on',
    'working_hours_start', 'working_hours_end', 'digest_frequency',
    'quiet_hours_start', 'quiet_hours_end',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    /** Minutes since last_seen_at before a member reads as away, then offline. */
    private const AWAY_AFTER_MINUTES = 5;

    private const OFFLINE_AFTER_MINUTES = 30;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'week_starts_on' => 'integer',
        ];
    }

    /* ------------------------------------------------------- memberships */

    /** @return HasMany<WorkspaceMember, $this> */
    public function workspaceMemberships(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    /** @return BelongsToMany<Workspace, $this, WorkspaceMember> */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->using(WorkspaceMember::class)
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    /** @return HasMany<Workspace, $this> */
    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    /* -------------------------------------------------------------- work */

    /** @return BelongsToMany<Project, $this> */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->withPivot(['role', 'added_by'])
            ->withTimestamps();
    }

    /** @return HasMany<Project, $this> */
    public function ledProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'lead_id');
    }

    /** @return BelongsToMany<Project, $this> */
    public function favoriteProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_favorites')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /** @return HasMany<Task, $this> */
    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    /** @return HasMany<Task, $this> */
    public function reportedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'reporter_id');
    }

    /** @return BelongsToMany<Task, $this> */
    public function watchedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_watchers')->withTimestamps();
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** @return HasMany<Attachment, $this> */
    public function uploads(): HasMany
    {
        return $this->hasMany(Attachment::class, 'uploaded_by');
    }

    /** @return HasMany<Activity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'actor_id');
    }

    /** @return HasMany<NotificationPreference, $this> */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /* ----------------------------------------------------------- helpers */

    /**
     * The workspace this person is currently working in.
     *
     * One seat is the common case today, so the first active membership wins.
     * When workspace switching lands this reads the choice from the session
     * and falls back to here.
     */
    public function currentWorkspace(): ?Workspace
    {
        return $this->workspaces()
            ->wherePivot('status', MemberStatus::Active->value)
            ->orderBy('workspace_members.id')
            ->first();
    }

    /** This person's role in a workspace, or null if they have no seat. */
    public function roleIn(Workspace|int|null $workspace): ?WorkspaceRole
    {
        if ($workspace === null) {
            return null;
        }

        $id = $workspace instanceof Workspace ? $workspace->id : $workspace;

        return $this->workspaceMemberships
            ->firstWhere('workspace_id', $id)?->role;
    }

    /**
     * Presence is derived, never stored — a stored "online" flag goes stale
     * the moment a browser closes without saying goodbye.
     */
    public function presence(): string
    {
        if ($this->last_seen_at === null) {
            return 'offline';
        }

        $minutes = $this->last_seen_at->diffInMinutes(now());

        return match (true) {
            $minutes < self::AWAY_AFTER_MINUTES => 'online',
            $minutes < self::OFFLINE_AFTER_MINUTES => 'away',
            default => 'offline',
        };
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];

        return strtoupper(
            substr($parts[0] ?? '', 0, 1).substr($parts[1] ?? '', 0, 1)
        );
    }

    /** Seed the notification matrix with the shipped defaults. */
    public function applyDefaultNotificationPreferences(): void
    {
        foreach (NotificationPreference::DEFAULTS as $event => $channels) {
            $this->notificationPreferences()->updateOrCreate(
                ['event' => $event],
                $channels,
            );
        }
    }
}
