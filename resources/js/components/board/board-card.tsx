import {
    Calendar,
    MessageSquare,
    MoveHorizontal,
    Paperclip,
} from 'lucide-react';
import { PriorityBadge } from '@/components/status-badge';
import { ProgressBar } from '@/components/progress-bar';
import { UserAvatar } from '@/components/user-avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Link } from '@inertiajs/react';
import { dueMeta, toneFill } from '@/lib/flowrive';
import { cn } from '@/lib/utils';
import { show as showTask } from '@/routes/tasks';
import type { BoardColumn, BoardTask } from '@/types';

export function BoardCard({
    task,
    columns,
    columnId,
    draggable,
    isDragging,
    onDragStart,
    onDragEnd,
    onMove,
}: {
    task: BoardTask;
    columns: BoardColumn[];
    columnId: number;
    draggable: boolean;
    isDragging: boolean;
    onDragStart: () => void;
    onDragEnd: () => void;
    onMove: (toColumn: number) => void;
}) {
    const due = dueMeta(task.dueDate);

    /**
     * Shift + arrow moves a focused card between columns, so the board is
     * usable without a pointer — HTML5 drag events never fire on touch or
     * from the keyboard.
     */
    function onKeyDown(event: React.KeyboardEvent) {
        if (!event.shiftKey) return;
        const direction =
            event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
        if (!direction) return;

        event.preventDefault();
        const index = columns.findIndex((column) => column.id === columnId);
        const next = columns[index + direction];
        if (next) onMove(next.id);
    }

    return (
        <article
            draggable={draggable}
            onDragStart={onDragStart}
            onDragEnd={onDragEnd}
            onKeyDown={onKeyDown}
            tabIndex={0}
            data-task={task.id}
            aria-roledescription="Draggable task card"
            aria-label={`${task.title}, ${task.priorityLabel} priority`}
            className={cn(
                'bg-card border-border group focus-visible:ring-ring rounded-xl border p-3 shadow-xs transition-[box-shadow,border-color,opacity,transform] focus-visible:ring-2 focus-visible:outline-none',
                draggable && 'cursor-grab active:cursor-grabbing',
                'hover:border-border-strong hover:shadow-sm',
                isDragging && 'rotate-[1.5deg] opacity-45',
            )}
        >
            <div className="flex items-start justify-between gap-2">
                <div className="flex flex-wrap items-center gap-1.5">
                    <PriorityBadge
                        label={task.priorityLabel}
                        tone={task.priorityTone}
                        icon={task.priorityIcon}
                    />
                    {task.labels.slice(0, 2).map((label) => (
                        <span
                            key={label.id}
                            className="bg-secondary text-ink-2 inline-flex h-5.5 items-center rounded-full px-2 text-[11px] font-medium"
                        >
                            {label.name}
                        </span>
                    ))}
                </div>

                {draggable && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                type="button"
                                aria-label={`Move ${task.title}`}
                                className="text-muted-foreground hover:bg-secondary hover:text-foreground -mt-1 -mr-1 rounded-md p-1 opacity-0 transition group-hover:opacity-100 focus-visible:opacity-100"
                            >
                                <MoveHorizontal className="size-3.5" aria-hidden />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-44">
                            <DropdownMenuLabel>Move to</DropdownMenuLabel>
                            {columns
                                .filter((column) => column.id !== columnId)
                                .map((column) => (
                                    <DropdownMenuItem
                                        key={column.id}
                                        onSelect={() => onMove(column.id)}
                                        className="gap-2"
                                    >
                                        <span
                                            className={cn(
                                                'size-2 rounded-full',
                                                toneFill[column.tone],
                                            )}
                                            aria-hidden
                                        />
                                        {column.name}
                                    </DropdownMenuItem>
                                ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>

            <Link
                href={showTask({ task: task.id })}
                className="text-foreground hover:text-brand-ink mt-2 block text-[13px] leading-5 font-medium"
                onClick={(event) => event.stopPropagation()}
            >
                {task.title}
            </Link>

            {task.subtasksTotal > 0 && (
                <div className="mt-2.5 flex items-center gap-2">
                    <ProgressBar
                        value={Math.round(
                            (task.subtasksCompleted / task.subtasksTotal) * 100,
                        )}
                        className="h-1 flex-1"
                        label={`${task.title} subtasks`}
                    />
                    <span className="text-muted-foreground text-[11px] tabular-nums">
                        {task.subtasksCompleted}/{task.subtasksTotal}
                    </span>
                </div>
            )}

            <div className="mt-3 flex items-center justify-between gap-2">
                <div className="text-muted-foreground flex items-center gap-2.5 text-[11px]">
                    {task.dueDate && (
                        <span className={cn('flex items-center gap-1', due.className)}>
                            <Calendar className="size-3.5" aria-hidden />
                            {due.label}
                        </span>
                    )}
                    {task.comments > 0 && (
                        <span className="flex items-center gap-1 tabular-nums">
                            <MessageSquare className="size-3.5" aria-hidden />
                            {task.comments}
                        </span>
                    )}
                    {task.attachments > 0 && (
                        <span className="flex items-center gap-1 tabular-nums">
                            <Paperclip className="size-3.5" aria-hidden />
                            {task.attachments}
                        </span>
                    )}
                </div>

                <div className="flex items-center gap-1.5">
                    <span className="text-muted-foreground text-[11px] font-medium">
                        {task.key}
                    </span>
                    {task.assignee && (
                        <UserAvatar person={task.assignee} size="xs" />
                    )}
                </div>
            </div>
        </article>
    );
}
