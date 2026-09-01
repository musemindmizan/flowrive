import {
    CircleCheckBig,
    Folder,
    SquareCheck,
    UserPlus,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Chip } from '@/components/status-badge';
import { Card } from '@/components/ui/card';
import { toneClasses } from '@/lib/flowrive';
import { cn } from '@/lib/utils';
import type { Stat } from '@/types';

const icons: Record<string, LucideIcon> = {
    folder: Folder,
    'square-check': SquareCheck,
    'circle-check-big': CircleCheckBig,
    users: Users,
    'user-plus': UserPlus,
};

export function StatCards({ stats }: { stats: Stat[] }) {
    return (
        <section
            aria-label="Workspace statistics"
            className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4"
        >
            {stats.map((stat) => {
                const Icon = icons[stat.icon] ?? Folder;

                return (
                    <Card key={stat.key} className="gap-0 p-4 sm:p-5">
                        <div className="flex items-start justify-between">
                            <span
                                className={cn(
                                    'flex size-9 items-center justify-center rounded-xl',
                                    toneClasses[stat.tone],
                                )}
                            >
                                <Icon className="size-4.5" aria-hidden />
                            </span>
                            {stat.badge && (
                                <Chip
                                    className={cn(
                                        stat.badgeTone &&
                                            toneClasses[stat.badgeTone],
                                    )}
                                >
                                    {stat.badge}
                                </Chip>
                            )}
                        </div>

                        <p className="text-foreground mt-3.5 text-2xl font-semibold tracking-tight tabular-nums">
                            {stat.value}
                        </p>
                        <p className="text-muted-foreground mt-0.5 text-[13px]">
                            {stat.label}
                        </p>
                        {stat.meta && (
                            <p className="text-muted-foreground mt-2 text-[11px]">
                                {stat.meta}
                            </p>
                        )}
                    </Card>
                );
            })}
        </section>
    );
}
