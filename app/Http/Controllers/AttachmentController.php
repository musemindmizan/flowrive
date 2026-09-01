<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Task;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * File attachments on a task.
 *
 * Only metadata is relational — the bytes go to the storage disk. The upload
 * is checked against the workspace's remaining quota before anything is
 * written, so a rejected file never leaves an orphan on disk.
 */
class AttachmentController extends Controller
{
    private const MAX_KILOBYTES = 25 * 1024;

    public function store(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('contribute', $task->project);

        $request->validate([
            'file' => ['required', 'file', 'max:'.self::MAX_KILOBYTES],
        ]);

        $file = $request->file('file');
        $workspace = $task->workspace;

        if ($workspace->storageUsed() + $file->getSize() > $workspace->storage_limit_bytes) {
            throw ValidationException::withMessages([
                'file' => 'This workspace has run out of storage. Remove a file and try again.',
            ]);
        }

        DB::transaction(function () use ($request, $task, $file) {
            $path = $file->store("workspaces/{$task->workspace_id}/tasks/{$task->id}");

            $attachment = Attachment::create([
                'workspace_id' => $task->workspace_id,
                'project_id' => $task->project_id,
                'task_id' => $task->id,
                'uploaded_by' => $request->user()->id,
                'disk' => config('filesystems.default'),
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => $file->getSize(),
                // Lets a workspace spot the same file uploaded twice
                'checksum' => hash_file('sha256', $file->getRealPath()),
            ]);

            $task->forceFill([
                'attachments_count' => $task->attachments()->count(),
            ])->save();

            Activity::log(
                workspaceId: $task->workspace_id,
                verb: 'attachment.uploaded',
                actorId: $request->user()->id,
                projectId: $task->project_id,
                taskId: $task->id,
                subject: $attachment,
                properties: ['name' => $attachment->original_name],
            );
        });

        return Flash::success(back(), "{$file->getClientOriginalName()} uploaded.");
    }

    public function destroy(Request $request, Attachment $attachment): RedirectResponse
    {
        $task = $attachment->task;
        $this->authorize('contribute', $attachment->project);

        $disk = $attachment->disk;
        $path = $attachment->path;

        DB::transaction(function () use ($attachment, $task) {
            $attachment->delete();

            $task?->forceFill([
                'attachments_count' => $task->attachments()->count(),
            ])->save();
        });

        // Delete the object after the row is gone: an orphaned file is a
        // wasted byte, an orphaned row is a broken download.
        Storage::disk($disk)->delete($path);

        return Flash::success(back(), 'File removed.');
    }

    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment->project);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)
            ->download($attachment->path, $attachment->original_name);
    }
}
