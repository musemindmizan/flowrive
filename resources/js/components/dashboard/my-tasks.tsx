import { useState } from 'react';
import { toast } from 'sonner';
import { PriorityBadge } from '@/components/status-badge';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { dueMeta } from '@/lib/flowrive';
import { cn } from '@/lib/utils';
import type { DashboardTask } from '@/types';

type Filter = 'all' | 'today' | 'week';

const filters: { key: Filter; label: string }[] = [
    { key: 'all', label: 'All' },
    { key: 'today', label: 'Today' },
    { key: 'week', label: 'This week' },
];

export function MyTasks({ tasks }: { tasks: DashboardTask[] }) {
    const [filter, setFilter] = useState<Filter>('all');
    const [completed, setCompleted] = useState<number[]>([]);

    const visible = tasks.filter((task) => {
        if (filter === 'all') return true;
        const { days } = dueMeta(task.dueDate);
        return filter === 'today' ? days <= 0 : days <= 7;
    });

    /**
     * Ticking a task strikes it through immediately and offers an undo — the
     * write lands when the task API arrives, so nothing is persisted yet.
     */
    function toggle(task: DashboardTask, checked: boolean) {
        setCompleted((current) =>
            checked
                ? [...current, task.id]
                : current.filter((id) => id !== task.id),
        );

        if (checked) {
            toast.success('Task completed', {
                description: task.title,
                action: {
                    label: 'Undo',
                    onClick: () =>
                        setCompleted((current) =>
                            current.filter((id) => id !== task.id),
                        ),
                },
            });
        }
    }

    return (
        <Card className="gap-0 overflow-hidden py-0" aria-labelledby="h-my-tasks">
            <div className="border-border flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
                <div>
                    <h2
                        id="h-my-tasks"
                        className="text-foreground text-base font-semibold tracking-tight"
                    >
                        My tasks
                    </h2>
                    <p className="text-muted-foreground mt-0.5 text-xs">
                        Assigned to you, soonest first
                    </p>
                </div>

                <div className="bg-secondary border-border inline-flex gap-0.5 rounded-lg border p-0.5">
                    {filters.map((item) => (
                        <button
                            key={item.key}
                            type="button"
                            onClick={() => setFilter(item.key)}
                            aria-pressed={filter === item.key}
                            className={cn(
                                'rounded-md px-2.5 py-1 text-xs font-medium transition-colors',
                                filter === item.key
                                    ? 'bg-card text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>
            </div>

            {visible.length === 0 ? (
                <p className="text-muted-foreground px-5 py-12 text-center text-[13px]">
                    {tasks.length === 0
                        ? 'Nothing is assigned to you right now.'
                        : 'No tasks in this window.'}
                </p>
            ) : (
                <ul className="divide-border divide-y">
                    {visible.map((task) => {
                        const due = dueMeta(task.dueDate);
                        const isDone = completed.includes(task.id);

                        return (
                            <li
                                key={task.id}
                                className={cn(
                                    'hover:bg-secondary/60 flex items-center gap-3 px-5 py-3 transition-colors',
                                    isDone && 'opacity-50',
                                )}
                            >
                                <Checkbox
                                    checked={isDone}
                                    onCheckedChange={(value) =>
                                        toggle(task, value === true)
                                    }
                                    aria-label={`Mark ${task.title} complete`}
                                />
                                <div className="min-w-0 flex-1">
                                    <p
                                        className={cn(
                                            'text-foreground truncate text-[13px] font-medium',
                                            isDone && 'line-through',
                                        )}
                                    >
                                        {task.title}
                                    </p>
                                    <p className="text-muted-foreground mt-0.5 truncate text-[11px]">
                                        {task.project.name} · {task.key}
                                    </p>
                                </div>
                                <div className="hidden sm:block">
                                    <PriorityBadge
                                        label={task.priorityLabel}
                                        tone={task.priorityTone}
                                        icon={task.priorityIcon}
                                    />
                                </div>
                                <span
                                    className={cn(
                                        'hidden text-xs whitespace-nowrap md:block',
                                        due.className,
                                    )}
                                >
                                    {due.text}
                                </span>
                            </li>
                        );
                    })}
                </ul>
            )}
        </Card>
    );
}
