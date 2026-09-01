<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

/**
 * File metadata. The bytes live on the storage disk; only this row is
 * relational. A file always belongs to a project and only sometimes to a task.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $project_id
 * @property int|null $task_id
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 */
#[Fillable([
    'workspace_id', 'project_id', 'task_id', 'uploaded_by', 'disk', 'path',
    'original_name', 'mime_type', 'size_bytes', 'checksum',
])]
class Attachment extends Model
{
    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
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
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Project-level files: the Files tab shows these alongside task files.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeProjectLevel(Builder $query): Builder
    {
        return $query->whereNull('task_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size_bytes, precision: 1);
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
