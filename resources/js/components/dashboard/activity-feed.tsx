import {
    Check,
    Columns3,
    Folder,
    MessageSquare,
    Paperclip,
    Plus,
    UserPlus,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { activityPhrase, activityTone, toneClasses } from '@/lib/flowrive';
import { cn } from '@/lib/utils';
import type { ActivityEntry } from '@/types';

const icons: Record<string, LucideIcon> = {
    plus: Plus,
    check: Check,
    'user-plus': UserPlus,
    'columns-3': Columns3,
    'message-square': MessageSquare,
    paperclip: Paperclip,
    folder: Folder,
};

export function ActivityFeed({ activity }: { activity: ActivityEntry[] }) {
    return (
        <Card className="gap-0 overflow-hidden py-0" aria-labelledby="h-activity">
            <div className="border-border border-b px-5 py-4">
                <h2
                    id="h-activity"
                    className="text-foreground text-base font-semibold tracking-tight"
                >
                    Activity feed
                </h2>
            </div>

            {activity.length === 0 ? (
                <p className="text-muted-foreground px-5 py-12 text-center text-[13px]">
                    Nothing has happened here yet.
                </p>
            ) : (
                <div className="p-5">
                    {/* The rail is drawn once behind the list rather than per row */}
                    <ol className="before:bg-border relative space-y-4 before:absolute before:top-2 before:bottom-2 before:left-[13px] before:w-px">
                        {activity.map((entry) => {
                            const tone =
                                activityTone[entry.verb] ??
                                ({ icon: 'plus', tone: 'neutral' } as const);
                            const Icon = icons[tone.icon] ?? Plus;

                            return (
                                <li
                                    key={entry.id}
                                    className="relative flex gap-3"
                                >
                                    <span
                                        className={cn(
                                            'ring-card z-10 flex size-6.5 flex-none items-center justify-center rounded-full ring-4',
                                            toneClasses[tone.tone],
                                        )}
                                    >
                                        <Icon
                                            className="size-3.5"
                                            aria-hidden
                                        />
                                    </span>

                                    <div className="min-w-0 flex-1">
                                        <p className="text-ink-2 text-[13px] leading-5">
                                            <span className="text-foreground font-semibold">
                                                {entry.actor?.name ??
                                                    'Someone'}
                                            </span>{' '}
                                            {activityPhrase(entry.verb)}{' '}
                                            {entry.target && (
                                                <span className="text-foreground font-medium">
                                                    {entry.target}
                                                </span>
                                            )}
                                        </p>
                                        {entry.meta && (
                                            <p className="bg-secondary text-ink-2 mt-1 inline-flex rounded-md px-2 py-0.5 text-[11px]">
                                                {entry.meta}
                                            </p>
                                        )}
                                        <p className="text-muted-foreground mt-0.5 text-[11px]">
                                            {entry.at}
                                            {entry.project && ` · ${entry.project}`}
                                        </p>
                                    </div>
                                </li>
                            );
                        })}
                    </ol>
                </div>
            )}
        </Card>
    );
}
