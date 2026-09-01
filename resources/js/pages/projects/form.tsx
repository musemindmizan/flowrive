import { Head, Link, useForm } from '@inertiajs/react';
import { Check, Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { ChipPicker, Field, Select, Textarea } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import {
    index as projectsIndex,
    store as storeProject,
    update as updateProject,
    destroy as destroyProject,
    board as boardRoute,
} from '@/routes/projects';

type Option = { value: string; label: string };
type PersonOption = { id: number; name: string; title: string | null };

type ProjectForm = {
    id: number;
    name: string;
    key: string;
    description: string | null;
    status: string;
    priority: string;
    visibility: string;
    color: string;
    start_date: string | null;
    deadline: string | null;
    lead_id: number | null;
    members: number[];
    tasksTotal: number;
};

const colors = [
    { value: 'brand', label: 'Violet', className: 'bg-brand-600' },
    { value: 'info', label: 'Blue', className: 'bg-info' },
    { value: 'success', label: 'Green', className: 'bg-success' },
    { value: 'warning', label: 'Amber', className: 'bg-warning' },
    { value: 'danger', label: 'Red', className: 'bg-danger' },
    { value: 'neutral', label: 'Slate', className: 'bg-ink-2' },
];

export default function ProjectForm({
    project,
    options,
}: {
    project: ProjectForm | null;
    options: {
        people: PersonOption[];
        statuses: Option[];
        priorities: Option[];
    };
}) {
    const isEdit = project !== null;

    const form = useForm({
        name: project?.name ?? '',
        key: project?.key ?? '',
        description: project?.description ?? '',
        status: project?.status ?? 'planning',
        priority: project?.priority ?? 'medium',
        visibility: project?.visibility ?? 'members',
        color: project?.color ?? 'brand',
        start_date: project?.start_date ?? '',
        deadline: project?.deadline ?? '',
        lead_id: project?.lead_id ?? null,
        members: project?.members ?? [],
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();

        if (isEdit) {
            form.patch(updateProject({ project: project.id }).url);
        } else {
            form.post(storeProject().url);
        }
    }

    /**
     * Suggest a key from the name while creating — three letters is what the
     * seeded projects use, and it stays editable.
     */
    function onNameChange(value: string) {
        form.setData((data) => ({
            ...data,
            name: value,
            key:
                isEdit || data.key !== suggestKey(data.name)
                    ? data.key
                    : suggestKey(value),
        }));
    }

    return (
        <>
            <Head title={isEdit ? `Edit ${project.name}` : 'Create project'} />

            <form onSubmit={submit} className="mx-auto w-full max-w-4xl p-4 sm:p-6">
                <header>
                    <h1 className="text-foreground text-2xl font-semibold tracking-tight">
                        {isEdit ? 'Edit project' : 'Create a project'}
                    </h1>
                    <p className="text-ink-2 mt-1.5 max-w-2xl text-[13px]">
                        {isEdit
                            ? 'Changes apply immediately for everyone on the project.'
                            : 'Projects group tasks, files and conversation. You can change any of this later.'}
                    </p>
                </header>

                <div className="mt-6 space-y-5">
                    <Card className="gap-0 py-0">
                        <div className="border-border border-b px-5 py-4">
                            <h2 className="text-foreground text-base font-semibold tracking-tight">
                                Project details
                            </h2>
                        </div>
                        <div className="space-y-5 px-5 py-5">
                            <div className="grid gap-5 sm:grid-cols-[1fr_9rem]">
                                <Field
                                    label="Project name"
                                    htmlFor="name"
                                    required
                                    error={form.errors.name}
                                >
                                    <Input
                                        id="name"
                                        value={form.data.name}
                                        onChange={(e) =>
                                            onNameChange(e.target.value)
                                        }
                                        placeholder="e.g. Website Redesign"
                                        autoFocus
                                    />
                                </Field>

                                <Field
                                    label="Key"
                                    htmlFor="key"
                                    required
                                    error={form.errors.key}
                                    hint={
                                        form.errors.key
                                            ? undefined
                                            : `${form.data.key || 'KEY'}-1, -2, …`
                                    }
                                >
                                    <Input
                                        id="key"
                                        value={form.data.key}
                                        onChange={(e) =>
                                            form.setData(
                                                'key',
                                                e.target.value.toUpperCase(),
                                            )
                                        }
                                        maxLength={6}
                                        className="font-medium tracking-wider uppercase"
                                    />
                                </Field>
                            </div>

                            <Field
                                label="Description"
                                htmlFor="description"
                                error={form.errors.description}
                            >
                                <Textarea
                                    id="description"
                                    rows={3}
                                    value={form.data.description ?? ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="What is this project trying to achieve, and how will you know it's done?"
                                />
                            </Field>

                            <div>
                                <Label className="mb-1.5 text-[13px]">
                                    Colour
                                </Label>
                                <div
                                    role="radiogroup"
                                    aria-label="Project colour"
                                    className="flex flex-wrap gap-2"
                                >
                                    {colors.map((color) => (
                                        <button
                                            key={color.value}
                                            type="button"
                                            role="radio"
                                            aria-checked={
                                                form.data.color === color.value
                                            }
                                            aria-label={color.label}
                                            onClick={() =>
                                                form.setData(
                                                    'color',
                                                    color.value,
                                                )
                                            }
                                            className={cn(
                                                'ring-offset-card size-8 rounded-lg transition',
                                                color.className,
                                                form.data.color === color.value
                                                    ? 'ring-ring ring-2 ring-offset-2'
                                                    : 'hover:ring-border-strong hover:ring-2 hover:ring-offset-2',
                                            )}
                                        />
                                    ))}
                                </div>
                            </div>
                        </div>
                    </Card>

                    <Card className="gap-0 py-0">
                        <div className="border-border border-b px-5 py-4">
                            <h2 className="text-foreground text-base font-semibold tracking-tight">
                                Timeline &amp; classification
                            </h2>
                        </div>
                        <div className="grid gap-5 px-5 py-5 sm:grid-cols-2">
                            <Field
                                label="Start date"
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
                                label="Deadline"
                                htmlFor="deadline"
                                error={form.errors.deadline}
                            >
                                <Input
                                    id="deadline"
                                    type="date"
                                    value={form.data.deadline ?? ''}
                                    onChange={(e) =>
                                        form.setData('deadline', e.target.value)
                                    }
                                />
                            </Field>

                            <Field
                                label="Status"
                                htmlFor="status"
                                error={form.errors.status}
                            >
                                <Select
                                    id="status"
                                    value={form.data.status}
                                    onChange={(e) =>
                                        form.setData('status', e.target.value)
                                    }
                                >
                                    {options.statuses.map((status) => (
                                        <option
                                            key={status.value}
                                            value={status.value}
                                        >
                                            {status.label}
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
                                        form.setData('priority', e.target.value)
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
                        </div>
                    </Card>

                    <Card className="gap-0 py-0">
                        <div className="border-border border-b px-5 py-4">
                            <h2 className="text-foreground text-base font-semibold tracking-tight">
                                Team &amp; access
                            </h2>
                        </div>
                        <div className="space-y-5 px-5 py-5">
                            <Field
                                label="Project lead"
                                htmlFor="lead_id"
                                error={form.errors.lead_id}
                                hint="The lead can edit the project and manage its members."
                            >
                                <Select
                                    id="lead_id"
                                    value={form.data.lead_id ?? ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'lead_id',
                                            e.target.value
                                                ? Number(e.target.value)
                                                : null,
                                        )
                                    }
                                >
                                    <option value="">You</option>
                                    {options.people.map((person) => (
                                        <option
                                            key={person.id}
                                            value={person.id}
                                        >
                                            {person.name}
                                            {person.title
                                                ? ` — ${person.title}`
                                                : ''}
                                        </option>
                                    ))}
                                </Select>
                            </Field>

                            <div>
                                <Label className="mb-1.5 text-[13px]">
                                    Members
                                </Label>
                                <ChipPicker
                                    options={options.people}
                                    selected={form.data.members}
                                    onToggle={(id) =>
                                        form.setData(
                                            'members',
                                            form.data.members.includes(id)
                                                ? form.data.members.filter(
                                                      (m) => m !== id,
                                                  )
                                                : [...form.data.members, id],
                                        )
                                    }
                                />
                                <p className="text-muted-foreground mt-2 text-xs">
                                    The lead is always a member. Everyone picked
                                    here can see the project and update its
                                    tasks.
                                </p>
                            </div>

                            <fieldset>
                                <legend className="text-foreground mb-1.5 text-[13px] font-medium">
                                    Who can see this project?
                                </legend>
                                <div className="space-y-2">
                                    {[
                                        [
                                            'members',
                                            'Project members only',
                                            'Only the people listed above.',
                                        ],
                                        [
                                            'workspace',
                                            'Everyone in the workspace',
                                            'Anyone with a seat can view it; only members can edit.',
                                        ],
                                    ].map(([value, title, hint]) => (
                                        <label
                                            key={value}
                                            className={cn(
                                                'flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition',
                                                form.data.visibility === value
                                                    ? 'border-brand-300 bg-brand-soft/50'
                                                    : 'border-border hover:bg-secondary',
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                name="visibility"
                                                className="accent-brand-600 mt-0.5"
                                                checked={
                                                    form.data.visibility ===
                                                    value
                                                }
                                                onChange={() =>
                                                    form.setData(
                                                        'visibility',
                                                        value,
                                                    )
                                                }
                                            />
                                            <span>
                                                <span className="text-foreground block text-[13px] font-medium">
                                                    {title}
                                                </span>
                                                <span className="text-muted-foreground mt-0.5 block text-xs">
                                                    {hint}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            </fieldset>
                        </div>
                    </Card>

                    {isEdit && (
                        <Card className="border-danger/30 gap-0 py-0">
                            <div className="border-danger/20 border-b px-5 py-4">
                                <h2 className="text-danger-ink text-base font-semibold tracking-tight">
                                    Danger zone
                                </h2>
                            </div>
                            <div className="flex flex-col gap-3 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p className="text-foreground text-[13px] font-medium">
                                        Delete this project
                                    </p>
                                    <p className="text-muted-foreground mt-0.5 text-xs">
                                        Permanently removes the project and its{' '}
                                        {project.tasksTotal} tasks.
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    onClick={() => {
                                        if (
                                            !confirm(
                                                `Delete ${project.name} and its ${project.tasksTotal} tasks? This cannot be undone.`,
                                            )
                                        ) {
                                            return;
                                        }
                                        form.delete(
                                            destroyProject({
                                                project: project.id,
                                            }).url,
                                            {
                                                onSuccess: () =>
                                                    toast.success(
                                                        'Project deleted.',
                                                    ),
                                            },
                                        );
                                    }}
                                >
                                    <Trash2 className="size-4" aria-hidden />
                                    Delete project
                                </Button>
                            </div>
                        </Card>
                    )}
                </div>

                <div className="border-border mt-6 flex flex-col-reverse gap-2.5 border-t pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-muted-foreground text-xs">
                        Fields marked <span className="text-danger">*</span> are
                        required.
                    </p>
                    <div className="flex flex-col-reverse gap-2.5 sm:flex-row">
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={
                                    isEdit
                                        ? boardRoute({ project: project.id })
                                        : projectsIndex()
                                }
                            >
                                Cancel
                            </Link>
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={form.processing}
                        >
                            {isEdit ? (
                                <Check className="size-4" aria-hidden />
                            ) : (
                                <Plus className="size-4" aria-hidden />
                            )}
                            {form.processing
                                ? 'Saving…'
                                : isEdit
                                  ? 'Save changes'
                                  : 'Create project'}
                        </Button>
                    </div>
                </div>
            </form>
        </>
    );
}

/** WEB from "Website Redesign", MOB from "Mobile App Development". */
function suggestKey(name: string): string {
    const words = name.trim().split(/\s+/).filter(Boolean);
    if (words.length === 0) return '';
    if (words.length === 1) return words[0].slice(0, 3).toUpperCase();

    return words
        .slice(0, 3)
        .map((word) => word[0])
        .join('')
        .toUpperCase();
}

ProjectForm.layout = {
    breadcrumbs: [{ title: 'Projects', href: projectsIndex() }],
};
