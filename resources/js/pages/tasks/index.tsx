import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    MessageSquare,
    Paperclip,
    Plus,
    SquareCheck,
} from 'lucide-react';
import { PriorityBadge, StatusBadge } from '@/components/status-badge';
import { TaskFilterBar } from '@/components/tasks/task-filters';
import { UserAvatar } from '@/components/user-avatar';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { dueMeta } from '@/lib/flowrive';
import { cn } from '@/lib/utils';
import { board } from '@/routes/projects';
import {
    index as tasksIndex,
    show as showTask,
    create as createTask,
} from '@/routes/tasks';
import type { TaskScope, TasksPageProps } from '@/types';

const scopes: { key: TaskScope; label: string; danger?: boolean }[] = [
    { key: 'all', label: 'All tasks' },
    { key: 'mine', label: 'Assigned to me' },
    { key: 'overdue', label: 'Overdue', danger: true },
    { key: 'done', label: 'Completed' },
];

export default function TasksIndex({
    tasks,
    meta,
    filters,
    counts,
    projectOptions,
    statusOptions,
    priorityOptions,
}: TasksPageProps) {
    function go(page: number) {
        router.get(
            tasksIndex().url,
            { ...filters, page },
            { preserveState: true, preserveScroll: true },
        );
    }

    function setScope(scope: TaskScope) {
        router.get(
            tasksIndex().url,
            { ...filters, scope, page: 1 },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Tasks" />

            <div className="mx-auto w-full max-w-7xl p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold tracking-tight">
                            Tasks
                        </h1>
                        <p className="text-ink-2 mt-1.5 text-[13px]">
                            <span className="text-foreground font-medium tabular-nums">
                                {counts.all} tasks
                            </span>{' '}
                            across {projectOptions.length} projects ·{' '}
                            <span className="text-danger-ink tabular-nums">
                                {counts.overdue} overdue
                            </span>{' '}
                            ·{' '}
                            <span className="tabular-nums">
                                {counts.mine} assigned to you
                            </span>
                        </p>
                    </div>
                    <Button size="sm" asChild>
                        <Link href={createTask()}>
                            <Plus className="size-4" aria-hidden />
                            New task
                        </Link>
                    </Button>
                </header>

                {/* Scope tabs */}
                <div
                    role="tablist"
                    aria-label="Task scope"
                    className="border-border mt-5 flex gap-1 overflow-x-auto border-b"
                >
                    {scopes.map((scope) => {
                        const active = filters.scope === scope.key;

                        return (
                            <button
                                key={scope.key}
                                type="button"
                                role="tab"
                                aria-selected={active}
                                onClick={() => setScope(scope.key)}
                                className={cn(
                                    '-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2.5 text-[13px] font-medium whitespace-nowrap transition-colors',
                                    active
                                        ? 'border-brand-600 text-brand-ink'
                                        : 'text-muted-foreground hover:text-foreground border-transparent',
                                )}
                            >
                                {scope.label}
                                <span
                                    className={cn(
                                        'rounded-full px-1.5 text-[11px] tabular-nums',
                                        scope.danger && counts[scope.key] > 0
                                            ? 'bg-danger-soft text-danger-ink'
                                            : 'bg-neutral-soft text-neutral-ink',
                                    )}
                                >
                                    {counts[scope.key]}
                                </span>
                            </button>
                        );
                    })}
                </div>

                <div className="mt-4">
                    <TaskFilterBar
                        filters={filters}
                        projectOptions={projectOptions}
                        statusOptions={statusOptions}
                        priorityOptions={priorityOptions}
                    />
                </div>

                {tasks.length === 0 ? (
                    <Card className="mt-4 px-6 py-16 text-center">
                        <span className="bg-secondary text-muted-foreground mx-auto flex size-14 items-center justify-center rounded-2xl">
                            <SquareCheck className="size-6" aria-hidden />
                        </span>
                        <h2 className="text-foreground mt-4 text-base font-semibold">
                            No tasks match these filters
                        </h2>
                        <p className="text-muted-foreground mx-auto mt-1.5 max-w-sm text-[13px]">
                            Adjust the filters above, or switch to another tab.
                        </p>
                    </Card>
                ) : (
                    <Card className="mt-4 gap-0 overflow-hidden py-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-[13px]">
                                <thead>
                                    <tr className="bg-secondary text-muted-foreground border-border border-b text-[11px] tracking-wide uppercase">
                                        <th className="px-5 py-2.5 text-left font-semibold">
                                            Task
                                        </th>
                                        <th className="hidden px-4 py-2.5 text-left font-semibold lg:table-cell">
                                            Project
                                        </th>
                                        <th className="px-4 py-2.5 text-left font-semibold">
                                            Assignee
                                        </th>
                                        <th className="hidden px-4 py-2.5 text-left font-semibold sm:table-cell">
                                            Priority
                                        </th>
                                        <th className="hidden px-4 py-2.5 text-left font-semibold md:table-cell">
                                            Status
                                        </th>
                                        <th className="px-5 py-2.5 text-left font-semibold">
                                            Due date
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {tasks.map((task) => {
                                        const due = dueMeta(task.dueDate);

                                        return (
                                            <tr
                                                key={task.id}
                                                className="border-border hover:bg-secondary/60 border-b transition-colors last:border-b-0"
                                            >
                                                <td className="px-5 py-3">
                                                    <Link
                                                        href={showTask({
                                                            task: task.id,
                                                        })}
                                                        className={cn(
                                                            'font-medium',
                                                            task.isComplete
                                                                ? 'text-muted-foreground line-through'
                                                                : 'text-foreground hover:text-brand-ink',
                                                        )}
                                                    >
                                                        {task.title}
                                                    </Link>
                                                    <div className="mt-1 flex flex-wrap items-center gap-1.5">
                                                        <span className="text-muted-foreground text-[11px]">
                                                            {task.key}
                                                        </span>
                                                        {task.labels.map(
                                                            (label) => (
                                                                <span
                                                                    key={label}
                                                                    className="bg-secondary text-ink-2 rounded-full px-1.5 text-[11px]"
                                                                >
                                                                    {label}
                                                                </span>
                                                            ),
                                                        )}
                                                        {task.comments > 0 && (
                                                            <span className="text-muted-foreground flex items-center gap-1 text-[11px] tabular-nums">
                                                                <MessageSquare
                                                                    className="size-3"
                                                                    aria-hidden
                                                                />
                                                                {task.comments}
                                                            </span>
                                                        )}
                                                        {task.attachments >
                                                            0 && (
                                                            <span className="text-muted-foreground flex items-center gap-1 text-[11px] tabular-nums">
                                                                <Paperclip
                                                                    className="size-3"
                                                                    aria-hidden
                                                                />
                                                                {
                                                                    task.attachments
                                                                }
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="hidden px-4 py-3 lg:table-cell">
                                                    <Link
                                                        href={board({
                                                            project:
                                                                task.project.id,
                                                        })}
                                                        className="text-muted-foreground hover:text-foreground flex items-center gap-2"
                                                    >
                                                        <span className="bg-secondary text-ink-2 flex size-6 flex-none items-center justify-center rounded text-[9px] font-semibold">
                                                            {task.project.key}
                                                        </span>
                                                        <span className="truncate">
                                                            {task.project.name}
                                                        </span>
                                                    </Link>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {task.assignee ? (
                                                        <div className="flex items-center gap-2">
                                                            <UserAvatar
                                                                person={
                                                                    task.assignee
                                                                }
                                                                size="sm"
                                                            />
                                                            <span className="text-muted-foreground hidden truncate xl:block">
                                                                {
                                                                    task
                                                                        .assignee
                                                                        .name
                                                                }
                                                            </span>
                                                        </div>
                                                    ) : (
                                                        <span className="text-muted-foreground text-[11px]">
                                                            Unassigned
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="hidden px-4 py-3 sm:table-cell">
                                                    <PriorityBadge
                                                        label={
                                                            task.priorityLabel
                                                        }
                                                        tone={task.priorityTone}
                                                        icon={task.priorityIcon}
                                                    />
                                                </td>
                                                <td className="hidden px-4 py-3 md:table-cell">
                                                    <StatusBadge
                                                        label={task.status}
                                                        tone={task.statusTone}
                                                    />
                                                </td>
                                                <td
                                                    className={cn(
                                                        'px-5 py-3 whitespace-nowrap',
                                                        due.className,
                                                    )}
                                                >
                                                    {task.dueDate
                                                        ? due.text
                                                        : '—'}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        <div className="border-border bg-secondary flex flex-col items-center justify-between gap-3 border-t px-5 py-3 sm:flex-row">
                            <p className="text-muted-foreground text-xs">
                                Showing{' '}
                                <span className="text-ink-2 font-medium tabular-nums">
                                    {meta.from}–{meta.to}
                                </span>{' '}
                                of{' '}
                                <span className="text-ink-2 font-medium tabular-nums">
                                    {meta.total}
                                </span>{' '}
                                tasks
                            </p>

                            <nav
                                aria-label="Pagination"
                                className="flex items-center gap-1"
                            >
                                <Button
                                    variant="outline"
                                    size="icon"
                                    className="size-8"
                                    disabled={meta.currentPage <= 1}
                                    onClick={() => go(meta.currentPage - 1)}
                                    aria-label="Previous page"
                                >
                                    <ChevronLeft className="size-4" aria-hidden />
                                </Button>
                                <span className="text-muted-foreground px-2 text-xs tabular-nums">
                                    Page {meta.currentPage} of {meta.lastPage}
                                </span>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    className="size-8"
                                    disabled={meta.currentPage >= meta.lastPage}
                                    onClick={() => go(meta.currentPage + 1)}
                                    aria-label="Next page"
                                >
                                    <ChevronRight
                                        className="size-4"
                                        aria-hidden
                                    />
                                </Button>
                            </nav>
                        </div>
                    </Card>
                )}
            </div>
        </>
    );
}

TasksIndex.layout = {
    breadcrumbs: [{ title: 'Tasks', href: tasksIndex() }],
};
