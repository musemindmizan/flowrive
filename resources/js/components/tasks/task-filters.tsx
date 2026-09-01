import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    ArrowUpDown,
    CircleDashed,
    Flag,
    Folder,
    RotateCcw,
    Search,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { index as tasksIndex } from '@/routes/tasks';
import type { TaskFilters, TasksPageProps } from '@/types';

const sortLabels: Record<TaskFilters['sort'], string> = {
    due: 'Due date',
    title: 'Title',
    priority: 'Priority',
    created: 'Recently created',
};

export function TaskFilterBar({
    filters,
    projectOptions,
    statusOptions,
    priorityOptions,
}: Pick<
    TasksPageProps,
    'filters' | 'projectOptions' | 'statusOptions' | 'priorityOptions'
>) {
    const [search, setSearch] = useState(filters.search);

    /** Debounced so a typed word is one request, not eight. */
    useEffect(() => {
        if (search === filters.search) return;
        const timer = setTimeout(() => apply({ search }), 300);
        return () => clearTimeout(timer);
    }, [search]); // eslint-disable-line react-hooks/exhaustive-deps

    function apply(next: Partial<TaskFilters & { page: number }>) {
        router.get(
            tasksIndex().url,
            // Any filter change resets to page 1 — staying on page 6 of a
            // narrower result set lands the reader on an empty screen.
            { ...filters, search, page: 1, ...next },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function toggle(key: 'status' | 'priority', value: string) {
        const current = filters[key];
        apply({
            [key]: current.includes(value)
                ? current.filter((item) => item !== value)
                : [...current, value],
        });
    }

    const selectedProject = projectOptions.find(
        (option) => option.id === filters.project,
    );

    const isFiltered =
        search !== '' ||
        filters.project !== null ||
        filters.status.length > 0 ||
        filters.priority.length > 0;

    return (
        <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div className="relative xl:max-w-xs xl:flex-1">
                <Search
                    className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                    aria-hidden
                />
                <Input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Search tasks by title or number…"
                    aria-label="Search tasks"
                    className="pl-9"
                />
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" size="sm">
                            <Folder className="size-4" aria-hidden />
                            {selectedProject?.name ?? 'All projects'}
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="w-60">
                        <DropdownMenuItem onSelect={() => apply({ project: null })}>
                            All projects
                        </DropdownMenuItem>
                        {projectOptions.map((option) => (
                            <DropdownMenuItem
                                key={option.id}
                                onSelect={() => apply({ project: option.id })}
                                className="gap-2"
                            >
                                <span className="bg-secondary text-ink-2 flex size-5 flex-none items-center justify-center rounded text-[9px] font-semibold">
                                    {option.key}
                                </span>
                                <span className="truncate">{option.name}</span>
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>

                <FacetMenu
                    icon={CircleDashed}
                    label="Status"
                    options={statusOptions}
                    selected={filters.status}
                    onToggle={(value) => toggle('status', value)}
                />

                <FacetMenu
                    icon={Flag}
                    label="Priority"
                    options={priorityOptions}
                    selected={filters.priority}
                    onToggle={(value) => toggle('priority', value)}
                />

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" size="sm">
                            <ArrowUpDown className="size-4" aria-hidden />
                            <span className="hidden sm:inline">Sort:</span>{' '}
                            {sortLabels[filters.sort]}
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-52">
                        <DropdownMenuLabel>Sort by</DropdownMenuLabel>
                        {(
                            Object.entries(sortLabels) as [
                                TaskFilters['sort'],
                                string,
                            ][]
                        ).map(([value, label]) => (
                            <DropdownMenuItem
                                key={value}
                                onSelect={() =>
                                    apply({
                                        sort: value,
                                        direction:
                                            filters.sort === value &&
                                            filters.direction === 'asc'
                                                ? 'desc'
                                                : 'asc',
                                    })
                                }
                            >
                                {label}
                                {filters.sort === value && (
                                    <span className="text-muted-foreground ml-auto text-[11px]">
                                        {filters.direction === 'asc' ? '↑' : '↓'}
                                    </span>
                                )}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>

                {isFiltered && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            setSearch('');
                            apply({
                                search: '',
                                project: null,
                                status: [],
                                priority: [],
                            });
                        }}
                    >
                        <RotateCcw className="size-4" aria-hidden />
                        Clear
                    </Button>
                )}
            </div>
        </div>
    );
}

function FacetMenu({
    icon: Icon,
    label,
    options,
    selected,
    onToggle,
}: {
    icon: typeof Flag;
    label: string;
    options: { value: string; label: string }[];
    selected: string[];
    onToggle: (value: string) => void;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm">
                    <Icon className="size-4" aria-hidden />
                    <span className="hidden sm:inline">{label}</span>
                    {selected.length > 0 && (
                        <span className="bg-brand-soft text-brand-ink ml-1 rounded-full px-1.5 text-[10px] font-semibold tabular-nums">
                            {selected.length}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48">
                <DropdownMenuLabel>Filter by {label.toLowerCase()}</DropdownMenuLabel>
                {options.map((option) => (
                    <DropdownMenuItem
                        key={option.value}
                        onSelect={(event) => {
                            event.preventDefault();
                            onToggle(option.value);
                        }}
                        className="gap-2.5"
                    >
                        <Checkbox
                            checked={selected.includes(option.value)}
                            aria-hidden
                            tabIndex={-1}
                        />
                        {option.label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
