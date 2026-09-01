import { Folder, SquareCheck } from 'lucide-react';
import { Chip } from '@/components/status-badge';
import { Card } from '@/components/ui/card';
import { countdown, dueMeta, formatDate } from '@/lib/flowrive';
import { cn } from '@/lib/utils';
import type { Deadline } from '@/types';

export function UpcomingDeadlines({ deadlines }: { deadlines: Deadline[] }) {
    return (
        <Card
            className="gap-0 overflow-hidden py-0"
            aria-labelledby="h-deadlines"
        >
            <div className="border-border flex items-center justify-between gap-4 border-b px-5 py-4">
                <h2
                    id="h-deadlines"
                    className="text-foreground text-base font-semibold tracking-tight"
                >
                    Upcoming deadlines
                </h2>
                <Chip className="bg-warning-soft text-warning-ink">
                    Next 14 days
                </Chip>
            </div>

            {deadlines.length === 0 ? (
                <p className="text-muted-foreground px-5 py-12 text-center text-[13px]">
                    Nothing falls due in the next two weeks.
                </p>
            ) : (
                <ul className="divide-border divide-y">
                    {deadlines.map((item) => {
                        const due = dueMeta(item.date);
                        const [month, day] = formatDate(item.date).split(' ');
                        const Icon =
                            item.type === 'task' ? SquareCheck : Folder;

                        return (
                            <li
                                key={item.id}
                                className="flex items-center gap-3 px-5 py-3"
                            >
                                {/* A date tile reads faster than a line of text */}
                                <span
                                    className={cn(
                                        'flex size-9 flex-none flex-col items-center justify-center rounded-lg',
                                        due.days <= 0
                                            ? 'bg-danger-soft text-danger-ink'
                                            : due.days <= 4
                                              ? 'bg-warning-soft text-warning-ink'
                                              : 'bg-secondary text-ink-2',
                                    )}
                                >
                                    <span className="text-[10px] leading-none font-semibold uppercase">
                                        {month}
                                    </span>
                                    <span className="text-[13px] leading-tight font-semibold tabular-nums">
                                        {day}
                                    </span>
                                </span>

                                <div className="min-w-0 flex-1">
                                    <p className="text-foreground truncate text-[13px] font-medium">
                                        {item.title}
                                    </p>
                                    <p className="text-muted-foreground mt-0.5 flex items-center gap-1.5 truncate text-[11px]">
                                        <Icon className="size-3" aria-hidden />
                                        {item.meta}
                                    </p>
                                </div>

                                <span
                                    className={cn(
                                        'flex-none text-[11px] font-medium whitespace-nowrap',
                                        due.className,
                                    )}
                                >
                                    {countdown(item.date)}
                                </span>
                            </li>
                        );
                    })}
                </ul>
            )}
        </Card>
    );
}
