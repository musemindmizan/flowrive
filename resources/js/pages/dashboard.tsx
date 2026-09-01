import { Head } from '@inertiajs/react';
import { Folder, Plus, SquareCheck, Users } from 'lucide-react';
import { ActivityFeed } from '@/components/dashboard/activity-feed';
import { MyTasks } from '@/components/dashboard/my-tasks';
import { ProjectOverview } from '@/components/dashboard/project-overview';
import { StatCards } from '@/components/dashboard/stat-cards';
import { TeamWorkload } from '@/components/dashboard/team-workload';
import { UpcomingDeadlines } from '@/components/dashboard/upcoming-deadlines';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Link } from '@inertiajs/react';
import { dashboard } from '@/routes';
import { create as createProject } from '@/routes/projects';
import { create as createTask } from '@/routes/tasks';
import { index as teamIndex } from '@/routes/team';
import type { DashboardProps } from '@/types';

export default function Dashboard({
    workspace,
    greeting,
    stats = [],
    projects = [],
    myTasks = [],
    deadlines = [],
    activity = [],
    workload = [],
}: DashboardProps) {
    const today = new Date().toLocaleDateString('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
    });

    if (!workspace) {
        return (
            <>
                <Head title="Dashboard" />
                <div className="p-4 sm:p-6">
                    <Card className="mx-auto max-w-md p-8 text-center">
                        <h1 className="text-foreground text-lg font-semibold">
                            You're not in a workspace yet
                        </h1>
                        <p className="text-muted-foreground mt-1.5 text-[13px] leading-6">
                            Ask an admin to invite you, or create a workspace to
                            start tracking work.
                        </p>
                    </Card>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Dashboard" />

            <div className="mx-auto w-full max-w-7xl p-4 sm:p-6">
                {/* Greeting */}
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-muted-foreground text-xs font-medium">
                            {today}
                        </p>
                        <h1 className="text-foreground mt-1 text-2xl font-semibold tracking-tight">
                            Good {greeting.part}, {greeting.name}
                        </h1>
                        <p className="text-ink-2 mt-1.5 text-[13px]">
                            {greeting.message}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={createTask()}>
                                <SquareCheck className="size-4" aria-hidden />
                                New task
                            </Link>
                        </Button>
                        <Button size="sm" asChild>
                            <Link href={createProject()}>
                                <Plus className="size-4" aria-hidden />
                                New project
                            </Link>
                        </Button>
                    </div>
                </header>

                {projects.length === 0 && myTasks.length === 0 ? (
                    <Card className="mt-6 px-6 py-16 text-center">
                        <span className="bg-brand-soft text-brand-ink mx-auto flex size-14 items-center justify-center rounded-2xl">
                            <Folder className="size-6" aria-hidden />
                        </span>
                        <h2 className="text-foreground mt-4 text-lg font-semibold">
                            Nothing to show yet
                        </h2>
                        <p className="text-muted-foreground mx-auto mt-1.5 max-w-sm text-[13px] leading-6">
                            Projects hold your tasks, files and conversation.
                            Create the first one and invite the people who'll
                            work on it.
                        </p>
                        <div className="mt-5 flex flex-wrap justify-center gap-2">
                            <Button asChild>
                                <Link href={createProject()}>
                                    <Plus className="size-4" aria-hidden />
                                    Create your first project
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={teamIndex()}>
                                    <Users className="size-4" aria-hidden />
                                    Invite your team
                                </Link>
                            </Button>
                        </div>
                    </Card>
                ) : (
                    <>
                        <div className="mt-6">
                            <StatCards stats={stats} />
                        </div>

                        <div className="mt-5 grid gap-5 xl:grid-cols-3">
                            <div className="space-y-5 xl:col-span-2">
                                <ProjectOverview projects={projects} />
                                <MyTasks tasks={myTasks} />
                            </div>

                            <div className="space-y-5">
                                <UpcomingDeadlines deadlines={deadlines} />
                                <ActivityFeed activity={activity} />
                                <TeamWorkload workload={workload} />
                            </div>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
