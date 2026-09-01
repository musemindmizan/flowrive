import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Briefcase, Mail, Search, Shield, User, UserPlus, X } from 'lucide-react';
import { toast } from 'sonner';
import { Field, Select, Textarea } from '@/components/form-field';
import { PresenceDot } from '@/components/presence-dot';
import { StatusBadge } from '@/components/status-badge';
import { UserAvatar } from '@/components/user-avatar';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { index as teamIndex, show as showMember } from '@/routes/team';
import {
    store as storeInvitation,
    destroy as destroyInvitation,
} from '@/routes/invitations';
import type { TeamPageProps } from '@/types';

const roleIcons: Record<string, typeof Shield> = {
    admin: Shield,
    manager: Briefcase,
    member: User,
};

export default function TeamIndex({
    members,
    filters,
    roleCounts,
    invitations,
    roleOptions,
    canInvite,
}: TeamPageProps) {
    const [search, setSearch] = useState(filters.search);

    useEffect(() => {
        if (search === filters.search) return;
        const timer = setTimeout(() => apply({ search }), 300);
        return () => clearTimeout(timer);
    }, [search]); // eslint-disable-line react-hooks/exhaustive-deps

    function apply(next: Partial<typeof filters>) {
        router.get(
            teamIndex().url,
            { ...filters, search, ...next },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Team" />

            <div className="mx-auto w-full max-w-7xl p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold tracking-tight">
                            Team
                        </h1>
                        <p className="text-ink-2 mt-1.5 text-[13px]">
                            <span className="text-foreground font-medium tabular-nums">
                                {members.length} people
                            </span>{' '}
                            in this workspace
                            {invitations.length > 0 && (
                                <>
                                    {' '}
                                    ·{' '}
                                    <span className="text-warning-ink tabular-nums">
                                        {invitations.length} pending invitation
                                        {invitations.length > 1 ? 's' : ''}
                                    </span>
                                </>
                            )}
                        </p>
                    </div>
                    {canInvite && <InviteDialog roleOptions={roleOptions} />}
                </header>

                {/* Role summary */}
                <section
                    aria-label="Roles"
                    className="mt-6 grid gap-3 sm:grid-cols-3"
                >
                    {roleOptions.map((role) => {
                        const Icon = roleIcons[role.value] ?? User;
                        const active = filters.role === role.value;

                        return (
                            <button
                                key={role.value}
                                type="button"
                                aria-pressed={active}
                                onClick={() =>
                                    apply({
                                        role: active ? null : role.value,
                                    })
                                }
                                className={cn(
                                    'text-left transition',
                                    active && 'ring-ring rounded-xl ring-2',
                                )}
                            >
                                <Card className="gap-0 p-4">
                                    <div className="flex items-center gap-3">
                                        <span
                                            className={cn(
                                                'flex size-9 items-center justify-center rounded-xl',
                                                role.value === 'admin'
                                                    ? 'bg-brand-soft text-brand-ink'
                                                    : role.value === 'manager'
                                                      ? 'bg-info-soft text-info-ink'
                                                      : 'bg-secondary text-ink-2',
                                            )}
                                        >
                                            <Icon
                                                className="size-4.5"
                                                aria-hidden
                                            />
                                        </span>
                                        <div className="min-w-0">
                                            <p className="text-foreground text-[13px] font-semibold">
                                                {role.label}
                                            </p>
                                            <p className="text-muted-foreground truncate text-[11px]">
                                                {role.description}
                                            </p>
                                        </div>
                                        <p className="text-foreground ml-auto text-xl font-semibold tabular-nums">
                                            {roleCounts[role.value] ?? 0}
                                        </p>
                                    </div>
                                </Card>
                            </button>
                        );
                    })}
                </section>

                {/* Toolbar */}
                <div className="mt-6 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div className="relative lg:max-w-xs lg:flex-1">
                        <Search
                            className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                            aria-hidden
                        />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by name, email or role…"
                            aria-label="Search members"
                            className="pl-9"
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="sm">
                                    {filters.status
                                        ? filters.status
                                        : 'All statuses'}
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-44">
                                <DropdownMenuItem
                                    onSelect={() => apply({ status: null })}
                                >
                                    All statuses
                                </DropdownMenuItem>
                                {['active', 'invited', 'deactivated'].map(
                                    (status) => (
                                        <DropdownMenuItem
                                            key={status}
                                            onSelect={() => apply({ status })}
                                            className="capitalize"
                                        >
                                            {status}
                                        </DropdownMenuItem>
                                    ),
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>

                        {(filters.role || filters.status || search) && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setSearch('');
                                    apply({
                                        search: '',
                                        role: null,
                                        status: null,
                                    });
                                }}
                            >
                                Clear
                            </Button>
                        )}
                    </div>
                </div>

                {/* Members */}
                {members.length === 0 ? (
                    <Card className="mt-4 px-6 py-16 text-center">
                        <span className="bg-secondary text-muted-foreground mx-auto flex size-14 items-center justify-center rounded-2xl">
                            <Search className="size-6" aria-hidden />
                        </span>
                        <h2 className="text-foreground mt-4 text-base font-semibold">
                            Nobody matches that search
                        </h2>
                        <p className="text-muted-foreground mx-auto mt-1.5 max-w-sm text-[13px]">
                            Try a different name, or invite someone new.
                        </p>
                    </Card>
                ) : (
                    <Card className="mt-4 gap-0 overflow-hidden py-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-[13px]">
                                <thead>
                                    <tr className="bg-secondary text-muted-foreground border-border border-b text-[11px] tracking-wide uppercase">
                                        <th className="px-5 py-2.5 text-left font-semibold">
                                            Member
                                        </th>
                                        <th className="hidden px-4 py-2.5 text-left font-semibold md:table-cell">
                                            Role
                                        </th>
                                        <th className="hidden px-4 py-2.5 text-left font-semibold lg:table-cell">
                                            Projects
                                        </th>
                                        <th className="hidden px-4 py-2.5 text-left font-semibold lg:table-cell">
                                            Open tasks
                                        </th>
                                        <th className="px-4 py-2.5 text-left font-semibold">
                                            Status
                                        </th>
                                        <th className="hidden px-5 py-2.5 text-left font-semibold xl:table-cell">
                                            Joined
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {members.map((member) => (
                                        <tr
                                            key={member.id}
                                            className="border-border hover:bg-secondary/60 border-b transition-colors last:border-b-0"
                                        >
                                            <td className="px-5 py-3">
                                                <div className="flex items-center gap-3">
                                                    <span className="relative inline-flex">
                                                        <UserAvatar
                                                            person={member}
                                                        />
                                                        <PresenceDot
                                                            presence={
                                                                member.presence
                                                            }
                                                        />
                                                    </span>
                                                    <div className="min-w-0">
                                                        <Link
                                                            href={showMember({
                                                                member:
                                                                    member.id,
                                                            })}
                                                            className="text-foreground hover:text-brand-ink block truncate font-medium"
                                                        >
                                                            {member.name}
                                                            {member.isYou && (
                                                                <span className="bg-neutral-soft text-neutral-ink ml-1.5 rounded-full px-1.5 text-[10px] font-medium">
                                                                    You
                                                                </span>
                                                            )}
                                                        </Link>
                                                        <p className="text-muted-foreground truncate text-[11px]">
                                                            {member.email}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="hidden px-4 py-3 md:table-cell">
                                                <span className="bg-neutral-soft text-neutral-ink rounded-full px-2 py-0.5 text-[11px] font-medium">
                                                    {member.roleLabel}
                                                </span>
                                                {member.title && (
                                                    <p className="text-muted-foreground mt-1 text-[11px]">
                                                        {member.title}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="hidden px-4 py-3 tabular-nums lg:table-cell">
                                                {member.projects}
                                            </td>
                                            <td className="hidden px-4 py-3 tabular-nums lg:table-cell">
                                                {member.openTasks}
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    label={member.statusLabel}
                                                    tone={member.statusTone}
                                                />
                                            </td>
                                            <td className="text-muted-foreground hidden px-5 py-3 whitespace-nowrap xl:table-cell">
                                                {member.joined ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                )}

                {/* Pending invitations */}
                {invitations.length > 0 && (
                    <Card className="mt-6 gap-0 overflow-hidden py-0">
                        <div className="border-border border-b px-5 py-4">
                            <h2 className="text-foreground text-base font-semibold tracking-tight">
                                Pending invitations
                            </h2>
                            <p className="text-muted-foreground mt-0.5 text-xs">
                                Invitations expire after 7 days.
                            </p>
                        </div>
                        <ul className="divide-border divide-y">
                            {invitations.map((invitation) => (
                                <li
                                    key={invitation.id}
                                    className="flex flex-wrap items-center gap-3 px-5 py-3.5"
                                >
                                    <span className="bg-secondary text-ink-2 flex size-8 flex-none items-center justify-center rounded-full">
                                        <Mail className="size-3.5" aria-hidden />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-foreground truncate text-[13px] font-medium">
                                            {invitation.email}
                                        </p>
                                        <p className="text-muted-foreground text-[11px]">
                                            Invited by {invitation.invitedBy} ·{' '}
                                            {invitation.sentAt} · expires{' '}
                                            {invitation.expiresAt}
                                        </p>
                                    </div>
                                    <span className="bg-warning-soft text-warning-ink rounded-full px-2 py-0.5 text-[11px] font-medium">
                                        {invitation.role}
                                    </span>
                                    {canInvite && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-8"
                                            aria-label={`Revoke invitation for ${invitation.email}`}
                                            onClick={() =>
                                                router.delete(
                                                    destroyInvitation({
                                                        invitation:
                                                            invitation.id,
                                                    }).url,
                                                    {
                                                        preserveScroll: true,
                                                        onSuccess: () =>
                                                            toast.success(
                                                                'Invitation revoked.',
                                                            ),
                                                    },
                                                )
                                            }
                                        >
                                            <X className="size-4" aria-hidden />
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}
            </div>
        </>
    );
}

function InviteDialog({
    roleOptions,
}: {
    roleOptions: TeamPageProps['roleOptions'];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ email: '', role: 'member', message: '' });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        form.post(storeInvitation().url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
                toast.success('Invitation sent.');
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <UserPlus className="size-4" aria-hidden />
                    Invite member
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Invite someone to the workspace</DialogTitle>
                        <DialogDescription>
                            They'll get an email with a link that stays valid
                            for 7 days.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        <Field
                            label="Email address"
                            htmlFor="invite-email"
                            required
                            error={form.errors.email}
                        >
                            <Input
                                id="invite-email"
                                type="email"
                                value={form.data.email}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                                placeholder="name@company.com"
                            />
                        </Field>

                        <Field
                            label="Role"
                            htmlFor="invite-role"
                            error={form.errors.role}
                            hint={
                                roleOptions.find(
                                    (r) => r.value === form.data.role,
                                )?.description
                            }
                        >
                            <Select
                                id="invite-role"
                                value={form.data.role}
                                onChange={(e) =>
                                    form.setData('role', e.target.value)
                                }
                            >
                                {roleOptions.map((role) => (
                                    <option key={role.value} value={role.value}>
                                        {role.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>

                        <Field
                            label="Personal message"
                            htmlFor="invite-message"
                            error={form.errors.message}
                        >
                            <Textarea
                                id="invite-message"
                                rows={3}
                                value={form.data.message}
                                onChange={(e) =>
                                    form.setData('message', e.target.value)
                                }
                                placeholder="Optional — a line about why you're inviting them."
                            />
                        </Field>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Send invitation
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

TeamIndex.layout = {
    breadcrumbs: [{ title: 'Team', href: teamIndex() }],
};
