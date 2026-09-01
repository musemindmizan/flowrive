import { Head, router } from '@inertiajs/react';
import {
    AtSign,
    Bell,
    Check,
    Circle,
    Folder,
    Inbox,
    Settings,
    SquareCheck,
    Trash2,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { toast } from 'sonner';
import { UserAvatar } from '@/components/user-avatar';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import {
    index as notificationsIndex,
    update as updateNotification,
    destroy as destroyNotification,
    clear as clearNotifications,
} from '@/routes/notifications';
import { readAll } from '@/routes/notifications';
import type { NotificationsPageProps } from '@/types';

const icons: Record<string, LucideIcon> = {
    'at-sign': AtSign,
    'square-check': SquareCheck,
    folder: Folder,
    settings: Settings,
};

const tones: Record<string, string> = {
    mention: 'bg-brand-soft text-brand-ink',
    task: 'bg-info-soft text-info-ink',
    project: 'bg-warning-soft text-warning-ink',
    system: 'bg-secondary text-ink-2',
};

export default function NotificationsIndex({
    notifications,
    filters,
    counts,
    categories,
}: NotificationsPageProps) {
    function apply(next: Partial<typeof filters>) {
        router.get(
            notificationsIndex().url,
            { ...filters, ...next },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function toggleRead(id: string, isRead: boolean) {
        router.patch(
            updateNotification({ notification: id }).url,
            { is_read: !isRead },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Notifications" />

            <div className="mx-auto w-full max-w-3xl p-4 sm:p-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold tracking-tight">
                            Notifications
                        </h1>
                        <p className="text-ink-2 mt-1.5 text-[13px]">
                            <span className="text-foreground font-medium tabular-nums">
                                {counts.unread} unread
                            </span>{' '}
                            of {counts.all}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={counts.unread === 0}
                            onClick={() =>
                                router.post(
                                    readAll().url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Check className="size-4" aria-hidden />
                            Mark all as read
                        </Button>
                        <Button
                            variant="destructive"
                            size="sm"
                            disabled={counts.all === 0}
                            onClick={() => {
                                if (
                                    !confirm(
                                        'Clear every notification? This cannot be undone.',
                                    )
                                ) {
                                    return;
                                }
                                router.delete(clearNotifications().url, {
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        toast.success('Notifications cleared.'),
                                });
                            }}
                        >
                            <Trash2 className="size-4" aria-hidden />
                            Clear all
                        </Button>
                    </div>
                </header>

                {/* Category tabs */}
                <div
                    role="tablist"
                    aria-label="Notification categories"
                    className="border-border mt-5 flex gap-1 overflow-x-auto border-b"
                >
                    <Tab
                        label="All"
                        count={counts.all}
                        active={filters.category === null}
                        onClick={() => apply({ category: null })}
                    />
                    {categories.map((category) => {
                        const Icon = icons[category.icon] ?? Bell;

                        return (
                            <Tab
                                key={category.value}
                                label={category.label}
                                icon={Icon}
                                count={counts[category.value] ?? 0}
                                active={filters.category === category.value}
                                onClick={() =>
                                    apply({ category: category.value })
                                }
                            />
                        );
                    })}
                </div>

                <div className="mt-3 flex items-center justify-between">
                    <label className="text-ink-2 flex cursor-pointer items-center gap-2.5 text-[13px]">
                        <input
                            type="checkbox"
                            className="accent-brand-600"
                            checked={filters.unread}
                            onChange={(e) =>
                                apply({ unread: e.target.checked })
                            }
                        />
                        Unread only
                    </label>
                    <p className="text-muted-foreground text-xs">
                        Notifications are kept for 30 days
                    </p>
                </div>

                <Card className="mt-3 gap-0 overflow-hidden py-0">
                    {notifications.length === 0 ? (
                        <div className="px-6 py-16 text-center">
                            <span className="bg-secondary text-muted-foreground mx-auto flex size-14 items-center justify-center rounded-2xl">
                                <Inbox className="size-6" aria-hidden />
                            </span>
                            <h2 className="text-foreground mt-4 text-base font-semibold">
                                You're all caught up
                            </h2>
                            <p className="text-muted-foreground mx-auto mt-1.5 max-w-xs text-[13px]">
                                Nothing here right now. New mentions and task
                                updates will show up in this list.
                            </p>
                        </div>
                    ) : (
                        <ul className="divide-border divide-y">
                            {notifications.map((notification) => (
                                <li
                                    key={notification.id}
                                    className={cn(
                                        'group relative flex gap-3 px-4 py-3.5 transition-colors sm:px-5',
                                        !notification.isRead &&
                                            'bg-brand-soft/30',
                                    )}
                                >
                                    {!notification.isRead && (
                                        <span
                                            className="bg-brand-500 absolute top-1/2 left-1.5 size-1.5 -translate-y-1/2 rounded-full"
                                            aria-hidden
                                        />
                                    )}

                                    <span className="relative flex-none">
                                        {notification.actor ? (
                                            <UserAvatar
                                                person={{
                                                    id: 0,
                                                    name: notification.actor,
                                                }}
                                            />
                                        ) : (
                                            <span
                                                className={cn(
                                                    'flex size-8 items-center justify-center rounded-full',
                                                    tones[
                                                        notification.category
                                                    ] ?? tones.system,
                                                )}
                                            >
                                                <Bell
                                                    className="size-4"
                                                    aria-hidden
                                                />
                                            </span>
                                        )}
                                    </span>

                                    <div className="min-w-0 flex-1">
                                        <p
                                            className={cn(
                                                'text-[13px] leading-5',
                                                notification.isRead
                                                    ? 'text-ink-2'
                                                    : 'text-foreground font-semibold',
                                            )}
                                        >
                                            {notification.title}
                                        </p>
                                        {notification.body && (
                                            <p className="text-muted-foreground mt-0.5 text-xs leading-5">
                                                {notification.body}
                                            </p>
                                        )}
                                        <p className="text-muted-foreground mt-1 flex items-center gap-1.5 text-[11px]">
                                            <span className="bg-neutral-soft text-neutral-ink rounded-full px-1.5 capitalize">
                                                {notification.category}
                                            </span>
                                            {notification.at}
                                        </p>
                                    </div>

                                    <div className="flex flex-none items-start gap-0.5 opacity-0 transition group-hover:opacity-100 focus-within:opacity-100">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-8"
                                            aria-label={
                                                notification.isRead
                                                    ? 'Mark as unread'
                                                    : 'Mark as read'
                                            }
                                            onClick={() =>
                                                toggleRead(
                                                    notification.id,
                                                    notification.isRead,
                                                )
                                            }
                                        >
                                            {notification.isRead ? (
                                                <Circle
                                                    className="size-4"
                                                    aria-hidden
                                                />
                                            ) : (
                                                <Check
                                                    className="size-4"
                                                    aria-hidden
                                                />
                                            )}
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-8"
                                            aria-label="Remove notification"
                                            onClick={() =>
                                                router.delete(
                                                    destroyNotification({
                                                        notification:
                                                            notification.id,
                                                    }).url,
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <X className="size-4" aria-hidden />
                                        </Button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>
        </>
    );
}

function Tab({
    label,
    icon: Icon,
    count,
    active,
    onClick,
}: {
    label: string;
    icon?: LucideIcon;
    count: number;
    active: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={active}
            onClick={onClick}
            className={cn(
                '-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2.5 text-[13px] font-medium whitespace-nowrap transition-colors',
                active
                    ? 'border-brand-600 text-brand-ink'
                    : 'text-muted-foreground hover:text-foreground border-transparent',
            )}
        >
            {Icon && <Icon className="size-3.5" aria-hidden />}
            {label}
            <span className="bg-neutral-soft text-neutral-ink rounded-full px-1.5 text-[11px] tabular-nums">
                {count}
            </span>
        </button>
    );
}

NotificationsIndex.layout = {
    breadcrumbs: [
        { title: 'Notifications', href: notificationsIndex() },
    ],
};
