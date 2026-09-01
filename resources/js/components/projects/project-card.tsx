import { Link } from '@inertiajs/react';
import { Calendar, SquareCheck } from 'lucide-react';
import { ProgressBar } from '@/components/progress-bar';
import { PriorityBadge, StatusBadge } from '@/components/status-badge';
import { AvatarGroup } from '@/components/user-avatar';
import { Card } from '@/components/ui/card';
import { dueMeta } from '@/lib/flowrive';
import { cn } from '@/lib/utils';
import { board } from '@/routes/projects';
import type { ProjectCard as Project } from '@/types';

export function ProjectCard({ project }: { project: Project }) {
    const due = dueMeta(project.deadline);

    return (
        <Card className="hover:border-border-strong flex flex-col gap-0 p-5 transition-[border-color,box-shadow,transform] hover:-translate-y-0.5 hover:shadow-md">
            <div className="flex items-start gap-3">
                <span className="bg-brand-soft text-brand-ink flex size-10 flex-none items-center justify-center rounded-xl text-[13px] font-semibold">
                    {project.key}
                </span>
                <div className="min-w-0 flex-1">
                    <Link
                        href={board({ project: project.id })}
                        className="text-foreground hover:text-brand-ink block truncate text-[15px] font-semibold tracking-tight"
                    >
                        {project.name}
                    </Link>
                    {project.lead && (
                        <p className="text-muted-foreground mt-0.5 truncate text-[11px]">
                            Led by {project.lead.name}
                        </p>
                    )}
                </div>
            </div>

            {project.description && (
                <p className="text-ink-2 mt-3.5 line-clamp-2 text-[13px] leading-5">
                    {project.description}
                </p>
            )}

            <div className="mt-4 flex flex-wrap items-center gap-2">
                <StatusBadge
                    label={project.statusLabel}
                    tone={project.statusTone}
                />
                <PriorityBadge
                    label={project.priorityLabel}
                    tone={project.priorityTone}
                    icon={project.priorityIcon}
                />
            </div>

            <div className="mt-4">
                <div className="flex items-baseline justify-between text-xs">
                    <span className="text-ink-2 font-medium">Progress</span>
                    <span className="text-foreground font-semibold tabular-nums">
                        {project.progress}%
                    </span>
                </div>
                <ProgressBar
                    value={project.progress}
                    className="mt-1.5"
                    label={`${project.name} progress`}
                />
            </div>

            <div className="border-border mt-4 flex items-center justify-between gap-3 border-t pt-4">
                <AvatarGroup people={project.members} max={4} size="sm" />
                <div className="text-muted-foreground flex items-center gap-3 text-[11px]">
                    <span className="flex items-center gap-1 tabular-nums">
                        <SquareCheck className="size-3.5" aria-hidden />
                        {project.tasksCompleted}/{project.tasksTotal}
                    </span>
                    <span className={cn('flex items-center gap-1', due.className)}>
                        <Calendar className="size-3.5" aria-hidden />
                        {due.label}
                    </span>
                </div>
            </div>
        </Card>
    );
}
