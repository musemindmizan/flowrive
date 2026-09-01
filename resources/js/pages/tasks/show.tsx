import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    Calendar,
    Check,
    Eye,
    FileText,
    Folder,
    Image as ImageIcon,
    Download,
    Pencil,
    Trash2,
    Upload,
    User as UserIcon,
} from 'lucide-react';
import { CommentThread } from '@/components/tasks/comment-thread';
import { ProgressBar } from '@/components/progress-bar';
import { PriorityBadge, StatusBadge } from '@/components/status-badge';
import { AvatarGroup, UserAvatar } from '@/components/user-avatar';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { activityPhrase, dueMeta } from '@/lib/flowrive';
import { cn } from '@/lib/utils';
import { board } from '@/routes/projects';
import {
    index as tasksIndex,
    edit as editTask,
    watch as watchTask,
    unwatch as unwatchTask,
} from '@/routes/tasks';
import { update as updateSubtask } from '@/routes/subtasks';
import {
    store as storeAttachment,
    download as downloadAttachment,
    destroy as destroyAttachment,
} from '@/routes/attachments';
import type { Auth, TaskShowProps } from '@/types';

export default function TaskShow({
    task,
    subtasks,
    attachments,
    comments,
    history,
    canContribute,
}: TaskShowProps) {
    const auth = usePage().props.auth as Auth;
    const [tab, setTab] = useState<'comments' | 'history'>('comments');
    const [uploading, setUploading] = useState(false);

    /** Multipart upload, then Inertia re-renders with the new file listed. */
    function upload(file: File) {
        setUploading(true);
        router.post(
            storeAttachment({ task: task.id }).url,
            { file },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => setUploading(false),
            },
        );
    }

    const due = dueMeta(task.dueDate);
    const doneSubtasks = subtasks.filter((s) => s.isCompleted).length;

    function toggleSubtask(id: number, isCompleted: boolean) {
        router.patch(
            updateSubtask({ subtask: id }).url,
            { is_completed: isCompleted },
            { preserveScroll: true, preserveState: false },
        );
    }

    return (
        <>
            <Head title={`${task.key} · ${task.title}`} />

            <div className="mx-auto w-full max-w-6xl p-4 sm:p-6">
                {/* Header */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="border-border text-muted-foreground rounded-full border px-2 py-0.5 text-[11px] font-medium">
                                {task.key}
                            </span>
                            <StatusBadge
                                label={task.status}
                                tone={task.statusTone}
                            />
                            <PriorityBadge
                                label={task.priorityLabel}
                                tone={task.priorityTone}
                                icon={task.priorityIcon}
                            />
                            {task.labels.map((label) => (
                                <span
                                    key={label.id}
                                    className="bg-secondary text-ink-2 rounded-full px-2 py-0.5 text-[11px] font-medium"
                                >
                                    {label.name}
                                </span>
                            ))}
                        </div>

                        <h1
                            className={cn(
                                'mt-2 text-2xl font-semibold tracking-tight',
                                task.isComplete
                                    ? 'text-muted-foreground line-through'
                                    : 'text-foreground',
                            )}
                        >
                            {task.title}
                        </h1>

                        <p className="text-muted-foreground mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                            <span>
                                Created by{' '}
                                <span className="text-ink-2 font-medium">
                                    {task.reporter?.name ?? 'someone'}
                                </span>{' '}
                                on {task.createdAt}
                            </span>
                            {task.dueDate && (
                                <span className={cn('flex items-center gap-1.5', due.className)}>
                                    <Calendar className="size-3.5" aria-hidden />
                                    Due {due.text}
                                </span>
                            )}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant={task.isWatching ? 'default' : 'outline'}
                            size="sm"
                            aria-pressed={task.isWatching}
                            onClick={() =>
                                task.isWatching
                                    ? router.delete(
                                          unwatchTask({ task: task.id }).url,
                                          { preserveScroll: true },
                                      )
                                    : router.post(
                                          watchTask({ task: task.id }).url,
                                          {},
                                          { preserveScroll: true },
                                      )
                            }
                        >
                            <Eye className="size-4" aria-hidden />
                            Watch
                            <span className="bg-neutral-soft text-neutral-ink ml-1 rounded-full px-1.5 text-[10px] tabular-nums">
                                {task.watchers.length}
                            </span>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={board({ project: task.project.id })}>
                                <Folder className="size-4" aria-hidden />
                                Open board
                            </Link>
                        </Button>
                        {canContribute && (
                            <Button size="sm" asChild>
                                <Link href={editTask({ task: task.id })}>
                                    <Pencil className="size-4" aria-hidden />
                                    Edit
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="mt-6 grid gap-5 lg:grid-cols-3">
                    {/* Main column */}
                    <div className="space-y-5 lg:col-span-2">
                        <Card className="gap-0 py-0">
                            <div className="border-border border-b px-5 py-4">
                                <h2 className="text-foreground text-base font-semibold tracking-tight">
                                    Description
                                </h2>
                            </div>
                            <div className="px-5 py-4">
                                {task.description ? (
                                    <p className="text-ink-2 text-[13px] leading-6 whitespace-pre-line">
                                        {task.description}
                                    </p>
                                ) : (
                                    <p className="text-muted-foreground text-[13px] italic">
                                        No description yet.
                                    </p>
                                )}
                            </div>
                        </Card>

                        {/* Subtasks */}
                        <Card className="gap-0 py-0">
                            <div className="border-border flex items-center justify-between gap-4 border-b px-5 py-4">
                                <h2 className="text-foreground flex items-center gap-2 text-base font-semibold tracking-tight">
                                    Subtasks
                                    <span className="bg-neutral-soft text-neutral-ink rounded-full px-2 text-[11px] font-medium tabular-nums">
                                        {doneSubtasks}/{subtasks.length}
                                    </span>
                                </h2>
                            </div>

                            {subtasks.length > 0 ? (
                                <>
                                    <div className="px-5 pt-4">
                                        <ProgressBar
                                            value={Math.round(
                                                (doneSubtasks /
                                                    subtasks.length) *
                                                    100,
                                            )}
                                            label="Subtask progress"
                                        />
                                    </div>
                                    <ul className="divide-border mt-3 divide-y">
                                        {subtasks.map((subtask) => (
                                            <li
                                                key={subtask.id}
                                                className="hover:bg-secondary/60 flex items-center gap-3 px-5 py-2.5 transition-colors"
                                            >
                                                <Checkbox
                                                    checked={
                                                        subtask.isCompleted
                                                    }
                                                    disabled={!canContribute}
                                                    onCheckedChange={(value) =>
                                                        toggleSubtask(
                                                            subtask.id,
                                                            value === true,
                                                        )
                                                    }
                                                    aria-label={subtask.title}
                                                />
                                                <span
                                                    className={cn(
                                                        'flex-1 text-[13px]',
                                                        subtask.isCompleted
                                                            ? 'text-muted-foreground line-through'
                                                            : 'text-ink-2',
                                                    )}
                                                >
                                                    {subtask.title}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </>
                            ) : (
                                <p className="text-muted-foreground px-5 py-8 text-center text-[13px]">
                                    No subtasks on this one.
                                </p>
                            )}
                        </Card>

                        {/* Attachments */}
                        <Card className="gap-0 py-0">
                            <div className="border-border flex items-center justify-between gap-4 border-b px-5 py-4">
                                <h2 className="text-foreground flex items-center gap-2 text-base font-semibold tracking-tight">
                                    Attachments
                                    <span className="bg-neutral-soft text-neutral-ink rounded-full px-2 text-[11px] font-medium tabular-nums">
                                        {attachments.length}
                                    </span>
                                </h2>
                            </div>

                            <div className="px-5 py-4">
                                {canContribute && (
                                    <label
                                        className={cn(
                                            'border-border-strong bg-secondary hover:border-brand-400 flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-6 py-6 text-center transition',
                                            uploading && 'pointer-events-none opacity-60',
                                        )}
                                    >
                                        <Upload
                                            className="text-muted-foreground size-5"
                                            aria-hidden
                                        />
                                        <span className="text-foreground mt-2 text-[13px] font-medium">
                                            {uploading
                                                ? 'Uploading…'
                                                : 'Click to upload a file'}
                                        </span>
                                        <span className="text-muted-foreground mt-0.5 text-[11px]">
                                            Up to 25 MB
                                        </span>
                                        <input
                                            type="file"
                                            className="sr-only"
                                            disabled={uploading}
                                            onChange={(event) => {
                                                const file =
                                                    event.target.files?.[0];
                                                if (!file) return;
                                                upload(file);
                                                event.target.value = '';
                                            }}
                                        />
                                    </label>
                                )}

                                {attachments.length > 0 && (
                                    <ul className="divide-border mt-4 divide-y">
                                        {attachments.map((file) => (
                                            <li
                                                key={file.id}
                                                className="flex items-center gap-3 py-3 first:pt-0"
                                            >
                                                <span
                                                    className={cn(
                                                        'flex size-9 flex-none items-center justify-center rounded-lg',
                                                        file.isImage
                                                            ? 'bg-brand-soft text-brand-ink'
                                                            : 'bg-secondary text-ink-2',
                                                    )}
                                                >
                                                    {file.isImage ? (
                                                        <ImageIcon
                                                            className="size-4.5"
                                                            aria-hidden
                                                        />
                                                    ) : (
                                                        <FileText
                                                            className="size-4.5"
                                                            aria-hidden
                                                        />
                                                    )}
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-foreground truncate text-[13px] font-medium">
                                                        {file.name}
                                                    </p>
                                                    <p className="text-muted-foreground mt-0.5 text-[11px]">
                                                        {file.size} ·{' '}
                                                        {file.uploadedBy} ·{' '}
                                                        {file.uploadedAt}
                                                    </p>
                                                </div>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8"
                                                    aria-label={`Download ${file.name}`}
                                                    asChild
                                                >
                                                    <a
                                                        href={
                                                            downloadAttachment({
                                                                attachment:
                                                                    file.id,
                                                            }).url
                                                        }
                                                    >
                                                        <Download
                                                            className="size-4"
                                                            aria-hidden
                                                        />
                                                    </a>
                                                </Button>
                                                {canContribute && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8"
                                                        aria-label={`Remove ${file.name}`}
                                                        onClick={() => {
                                                            if (
                                                                !confirm(
                                                                    `Remove ${file.name}?`,
                                                                )
                                                            )
                                                                return;
                                                            router.delete(
                                                                destroyAttachment(
                                                                    {
                                                                        attachment:
                                                                            file.id,
                                                                    },
                                                                ).url,
                                                                {
                                                                    preserveScroll:
                                                                        true,
                                                                },
                                                            );
                                                        }}
                                                    >
                                                        <Trash2
                                                            className="size-4"
                                                            aria-hidden
                                                        />
                                                    </Button>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                )}

                                {attachments.length === 0 && !canContribute && (
                                    <p className="text-muted-foreground py-6 text-center text-[13px]">
                                        No files on this task.
                                    </p>
                                )}
                            </div>
                        </Card>

                        {/* Comments + history */}
                        <Card className="gap-0 py-0">
                            <div
                                role="tablist"
                                aria-label="Task discussion"
                                className="border-border flex gap-1 border-b px-5"
                            >
                                {(
                                    [
                                        ['comments', `Comments`],
                                        ['history', 'Activity history'],
                                    ] as const
                                ).map(([key, label]) => (
                                    <button
                                        key={key}
                                        type="button"
                                        role="tab"
                                        aria-selected={tab === key}
                                        onClick={() => setTab(key)}
                                        className={cn(
                                            '-mb-px flex items-center gap-1.5 border-b-2 px-3 py-3 text-[13px] font-medium transition-colors',
                                            tab === key
                                                ? 'border-brand-600 text-brand-ink'
                                                : 'text-muted-foreground hover:text-foreground border-transparent',
                                        )}
                                    >
                                        {label}
                                        {key === 'comments' && (
                                            <span className="bg-neutral-soft text-neutral-ink rounded-full px-1.5 text-[11px] tabular-nums">
                                                {comments.length}
                                            </span>
                                        )}
                                    </button>
                                ))}
                            </div>

                            {tab === 'comments' ? (
                                <CommentThread
                                    taskId={task.id}
                                    comments={comments}
                                    me={{
                                        id: auth.user.id,
                                        name: auth.user.name,
                                    }}
                                    canContribute={canContribute}
                                />
                            ) : (
                                <div className="p-5">
                                    {history.length === 0 ? (
                                        <p className="text-muted-foreground py-6 text-center text-[13px]">
                                            Nothing has been recorded yet.
                                        </p>
                                    ) : (
                                        <ol className="before:bg-border relative space-y-4 before:absolute before:top-2 before:bottom-2 before:left-[13px] before:w-px">
                                            {history.map((entry) => (
                                                <li
                                                    key={entry.id}
                                                    className="relative flex gap-3"
                                                >
                                                    <span className="ring-card bg-secondary text-ink-2 z-10 flex size-6.5 flex-none items-center justify-center rounded-full ring-4">
                                                        <Check
                                                            className="size-3.5"
                                                            aria-hidden
                                                        />
                                                    </span>
                                                    <div className="min-w-0 flex-1">
                                                        <p className="text-ink-2 text-[13px] leading-5">
                                                            <span className="text-foreground font-semibold">
                                                                {entry.actor ??
                                                                    'Someone'}
                                                            </span>{' '}
                                                            {activityPhrase(
                                                                entry.verb,
                                                            )}{' '}
                                                            this task
                                                        </p>
                                                        {entry.meta && (
                                                            <p className="bg-secondary text-ink-2 mt-1 inline-flex rounded-md px-2 py-0.5 text-[11px]">
                                                                {entry.meta}
                                                            </p>
                                                        )}
                                                        <p className="text-muted-foreground mt-0.5 text-[11px]">
                                                            {entry.at}
                                                        </p>
                                                    </div>
                                                </li>
                                            ))}
                                        </ol>
                                    )}
                                </div>
                            )}
                        </Card>
                    </div>

                    {/* Details sidebar */}
                    <aside className="space-y-5">
                        <Card className="gap-0 py-0 lg:sticky lg:top-4">
                            <div className="border-border border-b px-5 py-4">
                                <h2 className="text-foreground text-base font-semibold tracking-tight">
                                    Details
                                </h2>
                            </div>
                            <dl className="space-y-4 px-5 py-4 text-[13px]">
                                <Row label="Assignee">
                                    {task.assignee ? (
                                        <span className="flex items-center gap-2">
                                            <UserAvatar
                                                person={task.assignee}
                                                size="sm"
                                            />
                                            <span className="text-foreground font-medium">
                                                {task.assignee.name}
                                            </span>
                                        </span>
                                    ) : (
                                        <span className="text-muted-foreground flex items-center gap-1.5">
                                            <UserIcon
                                                className="size-3.5"
                                                aria-hidden
                                            />
                                            Unassigned
                                        </span>
                                    )}
                                </Row>

                                <Row label="Reporter">
                                    {task.reporter && (
                                        <span className="flex items-center gap-2">
                                            <UserAvatar
                                                person={task.reporter}
                                                size="sm"
                                            />
                                            <span className="text-ink-2">
                                                {task.reporter.name}
                                            </span>
                                        </span>
                                    )}
                                </Row>

                                <Row label="Start">
                                    <span className="text-ink-2">
                                        {task.startDate ?? '—'}
                                    </span>
                                </Row>

                                <Row label="Due">
                                    <span className={due.className}>
                                        {task.dueDate ? due.text : '—'}
                                    </span>
                                </Row>

                                {task.estimateHours && (
                                    <Row label="Estimate">
                                        <span className="text-ink-2 tabular-nums">
                                            {task.estimateHours} h
                                        </span>
                                    </Row>
                                )}

                                <div className="border-border border-t pt-4">
                                    <dt className="text-muted-foreground mb-2 text-[11px] font-semibold tracking-wide uppercase">
                                        Project
                                    </dt>
                                    <dd>
                                        <Link
                                            href={board({
                                                project: task.project.id,
                                            })}
                                            className="border-border hover:bg-secondary flex items-center gap-2.5 rounded-xl border px-2.5 py-2 transition-colors"
                                        >
                                            <span className="bg-brand-soft text-brand-ink flex size-7 flex-none items-center justify-center rounded-lg text-[11px] font-semibold">
                                                {task.project.key}
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="text-foreground block truncate text-[13px] font-medium">
                                                    {task.project.name}
                                                </span>
                                                <span className="text-muted-foreground block text-[11px] tabular-nums">
                                                    {task.project.progress}%
                                                    complete
                                                </span>
                                            </span>
                                        </Link>
                                    </dd>
                                </div>

                                {task.watchers.length > 0 && (
                                    <div className="border-border border-t pt-4">
                                        <dt className="text-muted-foreground mb-2 text-[11px] font-semibold tracking-wide uppercase">
                                            Watchers
                                        </dt>
                                        <dd>
                                            <AvatarGroup
                                                people={task.watchers}
                                                max={5}
                                            />
                                        </dd>
                                    </div>
                                )}

                                <div className="border-border text-muted-foreground border-t pt-4 text-[11px]">
                                    <p>Created {task.createdAt}</p>
                                    <p className="mt-1">
                                        Last updated {task.updatedAt}
                                    </p>
                                </div>
                            </dl>
                        </Card>
                    </aside>
                </div>
            </div>
        </>
    );
}

function Row({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex items-center justify-between gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{children}</dd>
        </div>
    );
}

TaskShow.layout = {
    breadcrumbs: [{ title: 'Tasks', href: tasksIndex() }],
};
