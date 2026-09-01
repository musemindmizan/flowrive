import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    ArrowUpDown,
    Columns3,
    LayoutGrid,
    List,
    Plus,
    RotateCcw,
    Search,
    SlidersHorizontal,
} from 'lucide-react';
import { ProjectCard } from '@/components/projects/project-card';
import { ProgressBar } from '@/components/progress-bar';
import { PriorityBadge, StatusBadge } from '@/components/status-badge';
import { AvatarGroup } from '@/components/user-avatar';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { dueMeta } from '@/lib/flowrive';
import { cn } from '@/lib/utils';
import {
    index as projectsIndex,
    board,
    create as createProject,
} from '@/routes/projects';
import type { ProjectsPageProps } from '@/types';

const sortLabels: Record<string, string> = {
    deadline: 'Deadline',
    name: 'Name (A–Z)',
    progress: 'Progress',
    priority: 'Priority',
};

export default function ProjectsIndex({
    projects,
    filters,
    statusOptions,
    summary,
}: ProjectsPageProps) {
    const [search, setSearch] = useState(filters.search);
    const [view, setView] = useState<'grid' | 'list'>('grid');

    /**
     * Filtering happens server-side, so typing is debounced — one request per
     * pause rather than one per keystroke. `replace` keeps the back button
     * from filling up with every intermediate search.
     */
    useEffect(() => {
        if (search === filters.search) return;

        const timer = setTimeout(() => {
            router.get(
                projectsIndex().url,
                { ...filters, search },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timer);
    }, [search, filters]);

    function apply(next: Partial<typeof filters>) {
        router.get(
            projectsIndex().url,
            { ...filters, search, ...next },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function toggleStatus(value: string) {
        const status = filters.status.includes(value)
            ? filters.status.filter((s) => s !== value)
            : [...filters.status, value];

        apply({ status });
    }

    const isFiltered = search !== '' || filters.status.length > 0;

    return (
        <>
            <Head title="Projects" />

            <div className="mx-auto w-full max-w-7xl p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold tracking-tight">
                            Projects
                        </h1>
                        <p className="text-ink-2 mt-1.5 text-[13px]">
                            Everything your team is working on.{' '}
                            <span className="text-foreground font-medium tabular-nums">
                                {summary.projects} projects
                            </span>{' '}
                            ·{' '}
                            <span className="text-foreground font-medium tabular-nums">
                                {summary.active} active
                            </span>{' '}
                            ·{' '}
                            <span className="tabular-nums">
                                {summary.tasks} tasks in total
                            </span>
                            .
                        </p>
                    </div>
                    <Button size="sm" asChild>
                        <Link href={createProject()}>
                            <Plus className="size-4" aria-hidden />
                            Create project
                        </Link>
                    </Button>
                </header>

                {/* Toolbar */}
                <div className="mt-6 flex flex-col gap-3 lg:flex-row lg:items-center">
                    <div className="relative lg:max-w-xs lg:flex-1">
                        <Search
                            className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                            aria-hidden
                        />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search projects…"
                            aria-label="Search projects"
                            className="pl-9"
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="sm">
                                    <SlidersHorizontal className="size-4" aria-hidden />
                                    Status
                                    {filters.status.length > 0 && (
                                        <span className="bg-brand-soft text-brand-ink ml-1 rounded-full px-1.5 text-[10px] font-semibold tabular-nums">
                                            {filters.status.length}
                                        </span>
                                    )}
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" className="w-52">
                                <DropdownMenuLabel>
                                    Filter by status
                                </DropdownMenuLabel>
                                {statusOptions.map((option) => (
                                    <DropdownMenuItem
                                        key={option.value}
                                        onSelect={(event) => {
                                            event.preventDefault();
                                            toggleStatus(option.value);
                                        }}
                                        className="gap-2.5"
                                    >
                                        <Checkbox
                                            checked={filters.status.includes(
                                                option.value,
                                            )}
                                            aria-hidden
                                            tabIndex={-1}
                                        />
                                        {option.label}
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="sm">
                                    <ArrowUpDown className="size-4" aria-hidden />
                                    <span className="hidden sm:inline">
                                        Sort:
                                    </span>{' '}
                                    {sortLabels[filters.sort]}
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" className="w-48">
                                {Object.entries(sortLabels).map(
                                    ([value, label]) => (
                                        <DropdownMenuItem
                                            key={value}
                                            onSelect={() =>
                                                apply({
                                                    sort: value as typeof filters.sort,
                                                })
                                            }
                                        >
                                            {label}
                                        </DropdownMenuItem>
                                    ),
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <div className="bg-secondary border-border inline-flex gap-0.5 rounded-lg border p-0.5">
                            {(
                                [
                                    ['grid', LayoutGrid, 'Grid'],
                                    ['list', List, 'List'],
                                ] as const
                            ).map(([key, Icon, label]) => (
                                <button
                                    key={key}
                                    type="button"
                                    onClick={() => setView(key)}
                                    aria-pressed={view === key}
                                    className={cn(
                                        'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium transition-colors',
                                        view === key
                                            ? 'bg-card text-foreground shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    <Icon className="size-3.5" aria-hidden />
                                    <span className="hidden sm:inline">
                                        {label}
                                    </span>
                                </button>
                            ))}
                        </div>

                        {isFiltered && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setSearch('');
                                    apply({ search: '', status: [] });
                                }}
                            >
                                <RotateCcw className="size-4" aria-hidden />
                                Clear
                            </Button>
                        )}
                    </div>
                </div>

                {projects.length === 0 ? (
                    <Card className="mt-5 px-6 py-16 text-center">
                        <span className="bg-secondary text-muted-foreground mx-auto flex size-14 items-center justify-center rounded-2xl">
                            <Search className="size-6" aria-hidden />
                        </span>
                        <h2 className="text-foreground mt-4 text-base font-semibold">
                            No projects match your filters
                        </h2>
                        <p className="text-muted-foreground mx-auto mt-1.5 max-w-sm text-[13px]">
                            Try a different search term, or clear the filters to
                            see everything again.
                        </p>
                        <div className="mt-5">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    setSearch('');
                                    apply({ search: '', status: [] });
                                }}
                            >
                                <RotateCcw className="size-4" aria-hidden />
                                Clear search and filters
                            </Button>
                        </div>
                    </Card>
                ) : view === 'grid' ? (
                    <div className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {projects.map((project) => (
                            <ProjectCard key={project.id} project={project} />
                        ))}
                    </div>
                ) : (
                    <Card className="mt-5 gap-0 overflow-hidden py-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-[13px]">
                                <thead>
                                    <tr className="bg-secondary text-muted-foreground border-border border-b text-[11px] tracking-wide uppercase">
                                        <th className="px-5 py-2.5 text-left font-semibold">
                                            Project
                                        </th>
                                        <th className="px-4 py-2.5 text-left font-semibold">
                                            Status
                                        </th>
                                        <th className="hidden px-4 py-2.5 text-left font-semibold sm:table-cell">
                                            Priority
                                        </th>
                                        <th className="w-44 px-4 py-2.5 text-left font-semibold">
                                            Progress
                                        </th>
                                        <th className="hidden px-4 py-2.5 text-left font-semibold md:table-cell">
                                            Team
                                        </th>
                                        <th className="px-5 py-2.5 text-left font-semibold">
                                            Deadline
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
                                                            <Link
                                                                href={board({
                                                                    project:
                                                                        project.id,
                                                                })}
                                                                className="text-foreground hover:text-brand-ink block truncate font-medium"
                                                            >
                                                                {project.name}
                                                            </Link>
                                                            <p className="text-muted-foreground truncate text-[11px] tabular-nums">
                                                                {
                                                                    project.tasksCompleted
                                                                }
                                                                /
                                                                {
                                                                    project.tasksTotal
                                                                }{' '}
                                                                tasks ·{' '}
                                                                {project.files}{' '}
                                                                files
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <StatusBadge
                                                        label={
                                                            project.statusLabel
                                                        }
                                                        tone={
                                                            project.statusTone
                                                        }
                                                    />
                                                </td>
                                                <td className="hidden px-4 py-3 sm:table-cell">
                                                    <PriorityBadge
                                                        label={
                                                            project.priorityLabel
                                                        }
                                                        tone={
                                                            project.priorityTone
                                                        }
                                                        icon={
                                                            project.priorityIcon
                                                        }
                                                    />
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-2">
                                                        <ProgressBar
                                                            value={
                                                                project.progress
                                                            }
                                                            className="w-24"
                                                            label={`${project.name} progress`}
                                                        />
                                                        <span className="text-ink-2 text-xs font-medium tabular-nums">
                                                            {project.progress}%
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="hidden px-4 py-3 md:table-cell">
                                                    <AvatarGroup
                                                        people={project.members}
                                                        max={3}
                                                        size="xs"
                                                    />
                                                </td>
                                                <td
                                                    className={cn(
                                                        'px-5 py-3 whitespace-nowrap',
                                                        due.className,
                                                    )}
                                                >
                                                    {due.label}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                )}
            </div>
        </>
    );
}

ProjectsIndex.layout = {
    breadcrumbs: [{ title: 'Projects', href: projectsIndex() }],
};
