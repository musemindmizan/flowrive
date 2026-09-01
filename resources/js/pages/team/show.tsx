import { Head, Link } from '@inertiajs/react';
import { Briefcase, Calendar, Globe, Mail, Phone } from 'lucide-react';
import { ProgressBar } from '@/components/progress-bar';
import { PresenceDot } from '@/components/presence-dot';
import { PriorityBadge, StatusBadge } from '@/components/status-badge';
import { UserAvatar } from '@/components/user-avatar';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { dueMeta } from '@/lib/flowrive';
import { cn } from '@/lib/utils';
import { board } from '@/routes/projects';
import { show as showTask } from '@/routes/tasks';
import { index as teamIndex } from '@/routes/team';
import type { MemberPageProps } from '@/types';

export default function MemberShow({
    member,
    stats,
    projects,
    tasks,
}: MemberPageProps) {
    return (
        <>
            <Head title={member.name} />

            <div>
                {/* Cover */}
                <div className="from-brand-700 via-brand-600 to-brand-400 h-24 bg-gradient-to-r sm:h-28" />

                <div className="mx-auto w-full max-w-6xl px-4 sm:px-6">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div className="flex items-end gap-4">
                            <span className="ring-card relative -mt-10 inline-flex rounded-full ring-4 sm:-mt-12">
                                <UserAvatar
                                    person={member}
                                    size="lg"
                                    className="size-20 text-xl sm:size-22"
                                />
                                <PresenceDot
                                    presence={member.presence}
                                    className="size-4"
                                />
                            </span>
                            <div className="pb-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h1 className="text-foreground text-2xl font-semibold tracking-tight">
                                        {member.name}
                                    </h1>
                                    <span className="bg-neutral-soft text-neutral-ink rounded-full px-2 py-0.5 text-[11px] font-medium">
                                        {member.role}
                                    </span>
                                    <StatusBadge
                                        label={member.statusLabel}
                                        tone={member.statusTone}
                                    />
                                </div>
                                <p className="text-ink-2 mt-1 text-[13px]">
                                    {member.title ?? 'No job title set'}
                                </p>
                            </div>
                        </div>

                        <Button variant="outline" size="sm" asChild>
                            <a href={`mailto:${member.email}`}>
                                <Mail className="size-4" aria-hidden />
                                Email
                            </a>
                        </Button>
                    </div>

                    <dl className="border-border mt-5 grid grid-cols-2 gap-4 border-t pt-4 sm:grid-cols-4">
                        <Stat label="Open tasks" value={stats.openTasks} />
                        <Stat label="Completed" value={stats.completed} />
                        <Stat label="Projects" value={stats.projects} />
                        <Stat
                            label="Overdue"
                            value={stats.overdue}
                            tone={stats.overdue > 0 ? 'text-danger' : undefined}
                        />
                    </dl>

                    <div className="mt-6 grid gap-5 pb-6 lg:grid-cols-3">
                        <div className="space-y-5">
                            <Card className="gap-0 py-0">
                                <div className="border-border border-b px-5 py-4">
                                    <h2 className="text-foreground text-base font-semibold tracking-tight">
                                        About
                                    </h2>
                                </div>
                                <div className="space-y-4 px-5 py-4">
                                    {member.bio && (
                                        <p className="text-ink-2 text-[13px] leading-6">
                                            {member.bio}
                                        </p>
                                    )}
                                    <dl className="border-border space-y-3 border-t pt-4 text-[13px]">
                                        <Row icon={Mail} label="Email">
                                            <a
                                                href={`mailto:${member.email}`}
                                                className="text-brand-ink hover:underline"
                                            >
                                                {member.email}
                                            </a>
                                        </Row>
                                        {member.phone && (
                                            <Row icon={Phone} label="Phone">
                                                <span className="text-ink-2">
                                                    {member.phone}
                                                </span>
                                            </Row>
                                        )}
                                        {member.department && (
                                            <Row
                                                icon={Briefcase}
                                                label="Department"
                                            >
                                                <span className="text-ink-2">
                                                    {member.department}
                                                </span>
                                            </Row>
                                        )}
                                        <Row icon={Calendar} label="Joined">
                                            <span className="text-ink-2">
                                                {member.joined ?? '—'}
                                            </span>
                                        </Row>
                                        <Row icon={Globe} label="Timezone">
                                            <span className="text-ink-2">
                                                {member.timezone}
                                            </span>
                                        </Row>
                                    </dl>
                                </div>
                            </Card>
                        </div>

                        <div className="space-y-5 lg:col-span-2">
                            <Card className="gap-0 py-0">
                                <div className="border-border border-b px-5 py-4">
                                    <h2 className="text-foreground text-base font-semibold tracking-tight">
                                        Projects
                                    </h2>
                                </div>
                                {projects.length === 0 ? (
                                    <p className="text-muted-foreground px-5 py-10 text-center text-[13px]">
                                        Not on any project you can see.
                                    </p>
                                ) : (
                                    <ul className="divide-border divide-y">
                                        {projects.map((project) => (
                                            <li
                                                key={project.id}
                                                className="hover:bg-secondary/60 flex items-center gap-3 px-5 py-3.5 transition-colors"
                                            >
                                                <span className="bg-brand-soft text-brand-ink flex size-9 flex-none items-center justify-center rounded-lg text-[11px] font-semibold">
                                                    {project.key}
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <Link
                                                        href={board({
                                                            project: project.id,
                                                        })}
                                                        className="text-foreground hover:text-brand-ink block truncate text-[13px] font-medium"
                                                    >
                                                        {project.name}
                                                    </Link>
                                                    <div className="mt-1 flex items-center gap-2">
                                                        <ProgressBar
                                                            value={
                                                                project.progress
                                                            }
                                                            className="w-24"
                                                            label={`${project.name} progress`}
                                                        />
                                                        <span className="text-muted-foreground text-[11px] tabular-nums">
                                                            {project.progress}%
                                                        </span>
                                                    </div>
                                                </div>
                                                <StatusBadge
                                                    label={project.statusLabel}
                                                    tone={project.statusTone}
                                                />
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Card>

                            <Card className="gap-0 py-0">
                                <div className="border-border border-b px-5 py-4">
                                    <h2 className="text-foreground text-base font-semibold tracking-tight">
                                        Open tasks
                                    </h2>
                                </div>
                                {tasks.length === 0 ? (
                                    <p className="text-muted-foreground px-5 py-10 text-center text-[13px]">
                                        Nothing open right now.
                                    </p>
                                ) : (
                                    <ul className="divide-border divide-y">
                                        {tasks.map((task) => {
                                            const due = dueMeta(task.dueDate);

                                            return (
                                                <li
                                                    key={task.id}
                                                    className="hover:bg-secondary/60 flex items-center gap-3 px-5 py-3 transition-colors"
                                                >
                                                    <div className="min-w-0 flex-1">
                                                        <Link
                                                            href={showTask({
                                                                task: task.id,
                                                            })}
                                                            className="text-foreground hover:text-brand-ink block truncate text-[13px] font-medium"
                                                        >
                                                            {task.title}
                                                        </Link>
                                                        <p className="text-muted-foreground mt-0.5 truncate text-[11px]">
                                                            {task.key} ·{' '}
                                                            {task.project}
                                                        </p>
                                                    </div>
                                                    <span className="hidden sm:block">
                                                        <PriorityBadge
                                                            label={
                                                                task.priorityLabel
                                                            }
                                                            tone={
                                                                task.priorityTone
                                                            }
                                                            icon={
                                                                task.priorityIcon
                                                            }
                                                        />
                                                    </span>
                                                    <span className="hidden md:block">
                                                        <StatusBadge
                                                            label={task.status}
                                                            tone={
                                                                task.statusTone
                                                            }
                                                        />
                                                    </span>
                                                    <span
                                                        className={cn(
                                                            'text-xs whitespace-nowrap',
                                                            due.className,
                                                        )}
                                                    >
                                                        {task.dueDate
                                                            ? due.text
                                                            : '—'}
                                                    </span>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                )}
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

function Stat({
    label,
    value,
    tone,
}: {
    label: string;
    value: number;
    tone?: string;
}) {
    return (
        <div>
            <dt className="text-muted-foreground text-[11px]">{label}</dt>
            <dd
                className={cn(
                    'text-foreground mt-0.5 text-lg font-semibold tabular-nums',
                    tone,
                )}
            >
                {value}
            </dd>
        </div>
    );
}

function Row({
    icon: Icon,
    label,
    children,
}: {
    icon: typeof Mail;
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex items-center gap-2.5">
            <dt className="text-muted-foreground flex w-24 flex-none items-center gap-1.5">
                <Icon className="size-3.5" aria-hidden />
                {label}
            </dt>
            <dd className="min-w-0 truncate">{children}</dd>
        </div>
    );
}

MemberShow.layout = {
    breadcrumbs: [{ title: 'Team', href: teamIndex() }],
};
