import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Check, Plus, Trash2, X } from 'lucide-react';
import { toast } from 'sonner';
import { ChipPicker, Field, Select, Textarea } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    index as tasksIndex,
    store as storeTask,
    update as updateTask,
    destroy as destroyTask,
    show as showTask,
} from '@/routes/tasks';

type ProjectOption = {
    id: number;
    key: string;
    name: string;
    columns: { id: number; name: string }[];
};

type TaskForm = {
    id: number;
    key: string;
    project_id: number;
    board_column_id: number;
    title: string;
    description: string | null;
    priority: string;
    assignee_id: number | null;
    start_date: string | null;
    due_date: string | null;
    estimate_hours: string | null;
    labels: number[];
    subtasks: string[];
};

export default function TaskFormPage({
    task,
    defaultProject,
    options,
}: {
    task: TaskForm | null;
    defaultProject: number | null;
    options: {
        projects: ProjectOption[];
        people: { id: number; name: string }[];
        labels: { id: number; name: string }[];
        priorities: { value: string; label: string }[];
    };
}) {
    const isEdit = task !== null;
    const firstProject = options.projects[0];

    const form = useForm({
        project_id: task?.project_id ?? defaultProject ?? firstProject?.id ?? 0,
        board_column_id:
            task?.board_column_id ?? firstProject?.columns[0]?.id ?? 0,
        title: task?.title ?? '',
        description: task?.description ?? '',
        priority: task?.priority ?? 'medium',
        assignee_id: task?.assignee_id ?? null,
        start_date: task?.start_date ?? '',
        due_date: task?.due_date ?? '',
        estimate_hours: task?.estimate_hours ?? '',
        labels: task?.labels ?? [],
        subtasks: task?.subtasks ?? [],
    });

    const [subtaskDraft, setSubtaskDraft] = useState('');

    const project = useMemo(
        () => options.projects.find((p) => p.id === form.data.project_id),
        [options.projects, form.data.project_id],
    );

    /**
     * Columns belong to a project, so switching project has to re-point the
     * column too — otherwise the form would post a column from elsewhere and
     * the server would (rightly) reject it.
     */
    function changeProject(id: number) {
        const next = options.projects.find((p) => p.id === id);
        form.setData((data) => ({
            ...data,
            project_id: id,
            board_column_id: next?.columns[0]?.id ?? 0,
            labels: [],
        }));
    }

    function addSubtask() {
        const title = subtaskDraft.trim();
        if (!title) return;
        form.setData('subtasks', [...form.data.subtasks, title]);
        setSubtaskDraft('');
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();

        if (isEdit) {
            form.patch(updateTask({ task: task.id }).url);
        } else {
            form.post(storeTask().url);
        }
    }

    if (options.projects.length === 0) {
        return (
            <>
                <Head title="New task" />
                <div className="p-4 sm:p-6">
                    <Card className="mx-auto max-w-md p-8 text-center">
                        <h1 className="text-foreground text-lg font-semibold">
                            No project to add this to
                        </h1>
                        <p className="text-muted-foreground mt-1.5 text-[13px] leading-6">
                            A task lives inside a project. Create one first.
                        </p>
                    </Card>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={isEdit ? `Edit ${task.key}` : 'New task'} />

            <form onSubmit={submit} className="mx-auto w-full max-w-5xl p-4 sm:p-6">
                <header>
                    <h1 className="text-foreground text-2xl font-semibold tracking-tight">
                        {isEdit ? 'Edit task' : 'Create a task'}
                    </h1>
                    <p className="text-ink-2 mt-1.5 text-[13px]">
                        {isEdit
                            ? `Editing ${task.key}.`
                            : 'Give it a clear title and an owner — everything else can change later.'}
                    </p>
                </header>

                <div className="mt-6 grid gap-5 lg:grid-cols-3">
                    <div className="space-y-5 lg:col-span-2">
                        <Card className="gap-0 py-0">
                            <div className="space-y-5 px-5 py-5">
                                <Field
                                    label="Task title"
                                    htmlFor="title"
                                    required
                                    error={form.errors.title}
                                >
                                    <Input
                                        id="title"
                                        value={form.data.title}
                                        onChange={(e) =>
                                            form.setData('title', e.target.value)
                                        }
                                        placeholder="e.g. Design homepage hero section"
                                        autoFocus
                                    />
                                </Field>

                                <Field
                                    label="Description"
                                    htmlFor="description"
                                    error={form.errors.description}
                                    hint="What needs to happen, and how will you know it's done?"
                                >
                                    <Textarea
                                        id="description"
                                        rows={6}
                                        value={form.data.description ?? ''}
                                        onChange={(e) =>
                                            form.setData(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                        </Card>

                        {!isEdit && (
                            <Card className="gap-0 py-0">
                                <div className="border-border border-b px-5 py-4">
                                    <h2 className="text-foreground text-base font-semibold tracking-tight">
                                        Subtasks
                                    </h2>
                                </div>
                                <div className="px-5 py-5">
                                    {form.data.subtasks.length > 0 && (
                                        <ul className="mb-3 space-y-1.5">
                                            {form.data.subtasks.map(
                                                (title, index) => (
                                                    <li
                                                        key={`${title}-${index}`}
                                                        className="border-border bg-secondary flex items-center gap-2.5 rounded-lg border px-3 py-2"
                                                    >
                                                        <span className="text-ink-2 flex-1 text-[13px]">
                                                            {title}
                                                        </span>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                form.setData(
                                                                    'subtasks',
                                                                    form.data.subtasks.filter(
                                                                        (_, i) =>
                                                                            i !==
                                                                            index,
                                                                    ),
                                                                )
                                                            }
                                                            aria-label={`Remove ${title}`}
                                                            className="text-muted-foreground hover:text-danger-ink rounded p-1"
                                                        >
                                                            <X
                                                                className="size-3.5"
                                                                aria-hidden
                                                            />
                                                        </button>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    )}

                                    <div className="flex gap-2">
                                        <Input
                                            value={subtaskDraft}
                                            onChange={(e) =>
                                                setSubtaskDraft(e.target.value)
                                            }
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter') {
                                                    e.preventDefault();
                                                    addSubtask();
                                                }
                                            }}
                                            placeholder="Add a subtask and press Enter"
                                            aria-label="New subtask"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={addSubtask}
                                        >
                                            <Plus className="size-4" aria-hidden />
                                            Add
                                        </Button>
                                    </div>
                                </div>
                            </Card>
                        )}
                    </div>

                    <div className="space-y-5">
                        <Card className="gap-0 py-0">
                            <div className="border-border border-b px-5 py-4">
                                <h2 className="text-foreground text-base font-semibold tracking-tight">
                                    Properties
                                </h2>
                            </div>
                            <div className="space-y-4 px-5 py-5">
                                <Field
                                    label="Project"
                                    htmlFor="project_id"
                                    required
                                    error={form.errors.project_id}
                                >
                                    <Select
                                        id="project_id"
                                        value={form.data.project_id}
                                        disabled={isEdit}
                                        onChange={(e) =>
                                            changeProject(Number(e.target.value))
                                        }
                                    >
                                        {options.projects.map((option) => (
                                            <option
                                                key={option.id}
                                                value={option.id}
                                            >
                                                {option.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>

                                <Field
                                    label="Column"
                                    htmlFor="board_column_id"
                                    required
                                    error={form.errors.board_column_id}
                                >
                                    <Select
                                        id="board_column_id"
                                        value={form.data.board_column_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'board_column_id',
                                                Number(e.target.value),
                                            )
                                        }
                                    >
                                        {project?.columns.map((column) => (
                                            <option
                                                key={column.id}
                                                value={column.id}
                                            >
                                                {column.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>

                                <Field
                                    label="Assignee"
                                    htmlFor="assignee_id"
                                    error={form.errors.assignee_id}
                                >
                                    <Select
                                        id="assignee_id"
                                        value={form.data.assignee_id ?? ''}
                                        onChange={(e) =>
                                            form.setData(
                                                'assignee_id',
                                                e.target.value
                                                    ? Number(e.target.value)
                                                    : null,
                                            )
                                        }
                                    >
                                        <option value="">Unassigned</option>
                                        {options.people.map((person) => (
                                            <option
                                                key={person.id}
                                                value={person.id}
                                            >
                                                {person.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>

                                <Field
                                    label="Priority"
                                    htmlFor="priority"
                                    error={form.errors.priority}
                                >
                                    <Select
                                        id="priority"
                                        value={form.data.priority}
                                        onChange={(e) =>
                                            form.setData(
                                                'priority',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        {options.priorities.map((priority) => (
                                            <option
                                                key={priority.value}
                                                value={priority.value}
                                            >
                                                {priority.label}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>

                                <div className="grid grid-cols-2 gap-3">
                                    <Field
                                        label="Start"
                                        htmlFor="start_date"
                                        error={form.errors.start_date}
                                    >
                                        <Input
                                            id="start_date"
                                            type="date"
                                            value={form.data.start_date ?? ''}
                                            onChange={(e) =>
                                                form.setData(
                                                    'start_date',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    <Field
                                        label="Due"
                                        htmlFor="due_date"
                                        error={form.errors.due_date}
                                    >
                                        <Input
                                            id="due_date"
                                            type="date"
                                            value={form.data.due_date ?? ''}
                                            onChange={(e) =>
                                                form.setData(
                                                    'due_date',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                </div>

                                <Field
                                    label="Estimate (hours)"
                                    htmlFor="estimate_hours"
                                    error={form.errors.estimate_hours}
                                >
                                    <Input
                                        id="estimate_hours"
                                        type="number"
                                        min={0}
                                        step="0.5"
                                        value={form.data.estimate_hours ?? ''}
                                        onChange={(e) =>
                                            form.setData(
                                                'estimate_hours',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="4"
                                    />
                                </Field>

                                <div>
                                    <Label className="mb-1.5 text-[13px]">
                                        Labels
                                    </Label>
                                    <ChipPicker
                                        options={options.labels}
                                        selected={form.data.labels}
                                        onToggle={(id) =>
                                            form.setData(
                                                'labels',
                                                form.data.labels.includes(id)
                                                    ? form.data.labels.filter(
                                                          (l) => l !== id,
                                                      )
                                                    : [...form.data.labels, id],
                                            )
                                        }
                                        empty="This workspace has no labels yet."
                                    />
                                </div>
                            </div>
                        </Card>

                        {isEdit && (
                            <Card className="border-danger/30 gap-0 py-0">
                                <div className="flex items-center justify-between gap-3 px-5 py-4">
                                    <div>
                                        <p className="text-foreground text-[13px] font-medium">
                                            Delete task
                                        </p>
                                        <p className="text-muted-foreground mt-0.5 text-xs">
                                            Removes {task.key} and its comments.
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => {
                                            if (
                                                !confirm(
                                                    `Delete ${task.key}? This cannot be undone.`,
                                                )
                                            ) {
                                                return;
                                            }
                                            form.delete(
                                                destroyTask({ task: task.id })
                                                    .url,
                                                {
                                                    onSuccess: () =>
                                                        toast.success(
                                                            'Task deleted.',
                                                        ),
                                                },
                                            );
                                        }}
                                    >
                                        <Trash2
                                            className="size-4"
                                            aria-hidden
                                        />
                                    </Button>
                                </div>
                            </Card>
                        )}
                    </div>
                </div>

                <div className="border-border mt-6 flex flex-col-reverse gap-2.5 border-t pt-5 sm:flex-row sm:justify-end">
                    <Button variant="outline" size="sm" asChild>
                        <Link
                            href={
                                isEdit ? showTask({ task: task.id }) : tasksIndex()
                            }
                        >
                            Cancel
                        </Link>
                    </Button>
                    <Button type="submit" size="sm" disabled={form.processing}>
                        <Check className="size-4" aria-hidden />
                        {form.processing
                            ? 'Saving…'
                            : isEdit
                              ? 'Save changes'
                              : 'Create task'}
                    </Button>
                </div>
            </form>
        </>
    );
}

TaskFormPage.layout = {
    breadcrumbs: [{ title: 'Tasks', href: tasksIndex() }],
};
