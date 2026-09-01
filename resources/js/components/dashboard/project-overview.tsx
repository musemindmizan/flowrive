import { ProgressBar } from '@/components/progress-bar';
import { StatusBadge } from '@/components/status-badge';
import { AvatarGroup } from '@/components/user-avatar';
import { Card } from '@/components/ui/card';
import { dueMeta } from '@/lib/flowrive';
import { cn } from '@/lib/utils';
import type { DashboardProject } from '@/types';

/**
 * Wide screens get a table; below md the same rows become cards, because a
 * six-column table on a phone is a horizontal-scroll trap.
 */
export function ProjectOverview({
    projects,
}: {
    projects: DashboardProject[];
}) {
    return (
        <Card className="gap-0 overflow-hidden py-0" aria-labelledby="h-projects">
            <div className="border-border flex items-center justify-between gap-4 border-b px-5 py-4">
                <div>
                    <h2
                        id="h-projects"
                        className="text-foreground text-base font-semibold tracking-tight"
                    >
                        Project overview
                    </h2>
                    <p className="text-muted-foreground mt-0.5 text-xs">
                        Projects you lead or contribute to
                    </p>
                </div>
            </div>

            {projects.length === 0 ? (
                <p className="text-muted-foreground px-5 py-12 text-center text-[13px]">
                    No active projects yet.
                </p>
            ) : (
                <>
                    {/* Table — md and up */}
                    <div className="hidden overflow-x-auto md:block">
                        <table className="w-full text-[13px]">
                            <thead>
                                <tr className="bg-secondary text-muted-foreground border-border border-b text-[11px] tracking-wide uppercase">
                                    <th className="px-5 py-2.5 text-left font-semibold">
                                        Project
                                    </th>
                                    <th className="w-40 px-4 py-2.5 text-left font-semibold">
                                        Progress
                                    </th>
                                    <th className="px-4 py-2.5 text-left font-semibold">
                                        Members
                                    </th>
                                    <th className="px-4 py-2.5 text-left font-semibold">
                                        Deadline
                                    </th>
                                    <th className="px-5 py-2.5 text-left font-semibold">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {projects.map((project) => {
                                    const due = dueMeta(project.deadline);

                                    return (
                                        <tr
                                            key={project.id}
                                            className="border-border hover:bg-secondary/60 border-b transition-colors last:border-b-0"
                                        >
                                            <td className="px-5 py-3">
                                                <div className="flex items-center gap-2.5">
                                                    <span className="bg-brand-soft text-brand-ink flex size-8 flex-none items-center justify-center rounded-lg text-[11px] font-semibold">
                                                        {project.key}
                                                    </span>
                                                    <div className="min-w-0">
                                                        <p className="text-foreground truncate font-medium">
                                                            {project.name}
                                                        </p>
                                                        <p className="text-muted-foreground text-[11px] tabular-nums">
                                                            {
                                                                project.tasksCompleted
                                                            }
                                                            /
                                                            {project.tasksTotal}{' '}
                                                            tasks
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
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
                                            </td>
                                            <td className="px-4 py-3">
                                                <AvatarGroup
                                                    people={project.members}
                                                    max={3}
                                                    size="xs"
                                                />
                                            </td>
                                            <td
                                                className={cn(
                                                    'px-4 py-3 whitespace-nowrap',
                                                    due.className,
                                                )}
                                            >
                                                {due.label}
                                            </td>
                                            <td className="px-5 py-3">
                                                <StatusBadge
                                                    label={project.statusLabel}
                                                    tone={project.statusTone}
                                                />
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {/* Cards — below md */}
                    <div className="divide-border divide-y md:hidden">
                        {projects.map((project) => {
                            const due = dueMeta(project.deadline);

                            return (
                                <div key={project.id} className="p-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="text-foreground truncate text-[13px] font-semibold">
                                                {project.name}
                                            </p>
                                            <p className="text-muted-foreground mt-0.5 text-[11px]">
                                                {project.tasksCompleted}/
                                                {project.tasksTotal} tasks · due{' '}
                                                {due.label}
                                            </p>
                                        </div>
                                        <StatusBadge
                                            label={project.statusLabel}
                                            tone={project.statusTone}
                                        />
                                    </div>
                                    <div className="mt-3 flex items-center gap-3">
                                        <ProgressBar
                                            value={project.progress}
                                            className="flex-1"
                                            label={`${project.name} progress`}
                                        />
                                        <span className="text-ink-2 text-xs font-medium tabular-nums">
                                            {project.progress}%
                                        </span>
                                        <AvatarGroup
                                            people={project.members}
                                            max={3}
                                            size="xs"
                                        />
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </>
            )}
        </Card>
    );
}
