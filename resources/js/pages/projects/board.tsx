import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Info, Pencil, Plus } from 'lucide-react';
import { toast } from 'sonner';
import { BoardCard } from '@/components/board/board-card';
import { ProgressBar } from '@/components/progress-bar';
import { StatusBadge } from '@/components/status-badge';
import { AvatarGroup } from '@/components/user-avatar';
import { Button } from '@/components/ui/button';
import { dueMeta, toneFill } from '@/lib/flowrive';
import { cn } from '@/lib/utils';
import {
    index as projectsIndex,
    edit as editProject,
} from '@/routes/projects';
import { create as createTask, move as moveTask } from '@/routes/tasks';
import type { BoardColumn, BoardPageProps, BoardTask } from '@/types';

type Dragging = { task: BoardTask; fromColumn: number } | null;

export default function Board({
    project,
    columns: initialColumns,
    canContribute,
}: BoardPageProps) {
    const [columns, setColumns] = useState(initialColumns);
    const [dragging, setDragging] = useState<Dragging>(null);
    const [overColumn, setOverColumn] = useState<number | null>(null);
    const listRefs = useRef<Record<number, HTMLDivElement | null>>({});

    // The server is the source of truth; re-sync whenever it sends fresh props
    useEffect(() => setColumns(initialColumns), [initialColumns]);

    const due = dueMeta(project.deadline);

    /**
     * Applies the move locally first so the card lands under the cursor with
     * no round trip, then persists it. The request sends only the two
     * neighbours it was dropped between — one UPDATE, not a renumbered column.
     */
    function moveCard(task: BoardTask, toColumn: number, index?: number) {
        const previous = columns;

        const withoutTask = columns.map((column) => ({
            ...column,
            tasks: column.tasks.filter((t) => t.id !== task.id),
        }));

        const target = withoutTask.find((column) => column.id === toColumn);
        if (!target) return;

        const at = index ?? target.tasks.length;
        const before = target.tasks[at - 1]?.id ?? null;
        const after = target.tasks[at]?.id ?? null;

        target.tasks.splice(at, 0, task);
        setColumns(withoutTask);

        const columnName =
            withoutTask.find((c) => c.id === toColumn)?.name ?? 'another column';

        router.patch(
            moveTask({ task: task.id }).url,
            { column: toColumn, before, after },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () =>
                    toast.success(`Moved to ${columnName}`, {
                        description: task.title,
                    }),
                onError: () => {
                    setColumns(previous);
                    toast.error("That move didn't stick", {
                        description: 'The board has been put back as it was.',
                    });
                },
            },
        );
    }

    /** Which slot the pointer is hovering, by comparing card midpoints. */
    function dropIndex(columnId: number, clientY: number): number {
        const list = listRefs.current[columnId];
        if (!list) return 0;

        const cards = Array.from(
            list.querySelectorAll<HTMLElement>('[data-task]'),
        ).filter((card) => Number(card.dataset.task) !== dragging?.task.id);

        const index = cards.findIndex((card) => {
            const box = card.getBoundingClientRect();
            return clientY < box.top + box.height / 2;
        });

        return index === -1 ? cards.length : index;
    }

    return (
        <>
            <Head title={`${project.name} board`} />

            <div className="flex min-h-0 flex-1 flex-col">
                {/* Board header */}
                <div className="border-border bg-card flex-none border-b px-4 py-4 sm:px-6">
                    <div className="mx-auto flex max-w-7xl flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                        <div className="flex min-w-0 items-center gap-3">
                            <span className="bg-brand-soft text-brand-ink flex size-10 flex-none items-center justify-center rounded-xl text-[13px] font-semibold">
                                {project.key}
                            </span>
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h1 className="text-foreground truncate text-lg font-semibold tracking-tight">
                                        {project.name}
                                    </h1>
                                    <StatusBadge
                                        label={project.statusLabel}
                                        tone={project.statusTone}
                                    />
                                </div>
                                <div className="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1">
                                    <span className="text-muted-foreground text-xs tabular-nums">
                                        {project.tasksCompleted}/
                                        {project.tasksTotal} tasks
                                    </span>
                                    <div className="flex items-center gap-2">
                                        <ProgressBar
                                            value={project.progress}
                                            className="w-24"
                                            label={`${project.name} progress`}
                                        />
                                        <span className="text-ink-2 text-xs font-medium tabular-nums">
                                            {project.progress}%
                                        </span>
                                    </div>
                                    {project.deadline && (
                                        <span
                                            className={cn(
                                                'text-xs',
                                                due.className,
                                            )}
                                        >
                                            Due {due.label}
                                        </span>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-3">
                            <AvatarGroup people={project.members} max={5} />
                            <Button variant="outline" size="sm" asChild>
                                <Link href={editProject({ project: project.id })}>
                                    <Pencil className="size-4" aria-hidden />
                                    Edit
                                </Link>
                            </Button>
                            {canContribute && (
                                <Button size="sm" asChild>
                                    <Link
                                        href={createTask({
                                            query: { project: project.id },
                                        })}
                                    >
                                        <Plus className="size-4" aria-hidden />
                                        Add task
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>

                    {canContribute && (
                        <p className="text-muted-foreground mx-auto mt-2.5 flex max-w-7xl items-center gap-1.5 text-[11px]">
                            <Info className="size-3.5" aria-hidden />
                            Drag a card between columns, or focus one and press
                            <kbd className="border-border bg-secondary rounded border px-1 font-sans">
                                Shift
                            </kbd>
                            +
                            <kbd className="border-border bg-secondary rounded border px-1 font-sans">
                                ←
                            </kbd>
                            <kbd className="border-border bg-secondary rounded border px-1 font-sans">
                                →
                            </kbd>
                            to move it.
                        </p>
                    )}
                </div>

                {/* Columns */}
                <div className="min-h-0 flex-1 overflow-auto p-4 sm:p-6">
                    <div className="flex min-h-[26rem] items-start gap-4 pb-2">
                        {columns.map((column) => (
                            <section
                                key={column.id}
                                aria-label={`${column.name} column`}
                                className="bg-secondary border-border flex w-76 flex-none flex-col rounded-xl border"
                            >
                                <header className="flex flex-none items-center gap-2 px-3 py-2.5">
                                    <span
                                        className={cn(
                                            'size-2 flex-none rounded-full',
                                            toneFill[column.tone],
                                        )}
                                        aria-hidden
                                    />
                                    <h2 className="text-foreground text-[13px] font-semibold">
                                        {column.name}
                                    </h2>
                                    <span className="bg-neutral-soft text-neutral-ink rounded-full px-1.5 text-[11px] font-medium tabular-nums">
                                        {column.tasks.length}
                                    </span>
                                </header>

                                <div
                                    ref={(element) => {
                                        listRefs.current[column.id] = element;
                                    }}
                                    onDragOver={(event) => {
                                        if (!dragging) return;
                                        event.preventDefault();
                                        setOverColumn(column.id);
                                    }}
                                    onDragLeave={(event) => {
                                        if (
                                            !event.currentTarget.contains(
                                                event.relatedTarget as Node,
                                            )
                                        ) {
                                            setOverColumn(null);
                                        }
                                    }}
                                    onDrop={(event) => {
                                        if (!dragging) return;
                                        event.preventDefault();
                                        setOverColumn(null);
                                        moveCard(
                                            dragging.task,
                                            column.id,
                                            dropIndex(column.id, event.clientY),
                                        );
                                        setDragging(null);
                                    }}
                                    className={cn(
                                        'flex min-h-20 flex-1 flex-col gap-2 rounded-b-xl p-2 transition-colors',
                                        overColumn === column.id &&
                                            'bg-brand-soft/60 outline-brand-400 -outline-offset-4 outline-2 outline-dashed',
                                    )}
                                >
                                    {column.tasks.map((task) => (
                                        <BoardCard
                                            key={task.id}
                                            task={task}
                                            columns={columns}
                                            columnId={column.id}
                                            draggable={canContribute}
                                            isDragging={
                                                dragging?.task.id === task.id
                                            }
                                            onDragStart={() =>
                                                setDragging({
                                                    task,
                                                    fromColumn: column.id,
                                                })
                                            }
                                            onDragEnd={() => {
                                                setDragging(null);
                                                setOverColumn(null);
                                            }}
                                            onMove={(toColumn) =>
                                                moveCard(task, toColumn)
                                            }
                                        />
                                    ))}

                                    {column.tasks.length === 0 && (
                                        <p className="text-muted-foreground px-2 py-6 text-center text-xs">
                                            Nothing here yet.
                                        </p>
                                    )}
                                </div>
                            </section>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}

Board.layout = {
    breadcrumbs: [{ title: 'Projects', href: projectsIndex() }],
};
