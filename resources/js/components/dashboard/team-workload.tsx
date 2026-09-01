import { ProgressBar } from '@/components/progress-bar';
import { UserAvatar } from '@/components/user-avatar';
import { Card } from '@/components/ui/card';
import type { WorkloadRow } from '@/types';

/**
 * Who is carrying what. The bar's tone is a warning signal, so the open count
 * is printed beside it rather than left to the colour.
 */
export function TeamWorkload({ workload }: { workload: WorkloadRow[] }) {
    return (
        <Card className="gap-0 overflow-hidden py-0" aria-labelledby="h-workload">
            <div className="border-border border-b px-5 py-4">
                <h2
                    id="h-workload"
                    className="text-foreground text-base font-semibold tracking-tight"
                >
                    Team workload
                </h2>
                <p className="text-muted-foreground mt-0.5 text-xs">
                    Open tasks per person
                </p>
            </div>

            {workload.length === 0 ? (
                <p className="text-muted-foreground px-5 py-12 text-center text-[13px]">
                    No open tasks are assigned.
                </p>
            ) : (
                <div className="space-y-3.5 p-5">
                    {workload.map((row) => (
                        <div key={row.id} className="flex items-center gap-3">
                            <UserAvatar
                                person={{ id: row.id, name: row.name }}
                                size="sm"
                            />
                            <div className="min-w-0 flex-1">
                                <div className="flex items-baseline justify-between gap-2">
                                    <p className="text-foreground truncate text-[13px] font-medium">
                                        {row.name}
                                    </p>
                                    <p className="text-muted-foreground flex-none text-[11px] tabular-nums">
                                        {row.open} open
                                    </p>
                                </div>
                                <ProgressBar
                                    value={row.percent}
                                    tone={row.tone}
                                    className="mt-1.5"
                                    label={`${row.name} workload`}
                                />
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </Card>
    );
}
