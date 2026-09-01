import type { Tone } from '@/types';

/* -------------------------------------------------------------- avatars */

/** Two-letter initials, the fallback when someone has no photo. */
export function initials(name: string): string {
    const parts = name.trim().split(/\s+/);

    return (parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '');
}

/**
 * Stable per-name colour so the same person is always the same swatch —
 * derived rather than stored, matching the server-side helper.
 */
export function avatarClass(name: string): string {
    let sum = 0;
    for (let i = 0; i < name.length; i++) sum += name.charCodeAt(i);

    return `av-${(sum % 8) + 1}`;
}

/* ---------------------------------------------------------------- tones */

/** Soft background + readable foreground for a badge or icon tile. */
export const toneClasses: Record<Tone, string> = {
    brand: 'bg-brand-soft text-brand-ink',
    success: 'bg-success-soft text-success-ink',
    warning: 'bg-warning-soft text-warning-ink',
    danger: 'bg-danger-soft text-danger-ink',
    info: 'bg-info-soft text-info-ink',
    neutral: 'bg-neutral-soft text-neutral-ink',
};

/** Solid fill, used for progress bars and status dots. */
export const toneFill: Record<Tone, string> = {
    brand: 'bg-brand-600',
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
    info: 'bg-info',
    neutral: 'bg-border-strong',
};

/* ----------------------------------------------------------------- dates */

const DAY = 86_400_000;

function startOfDay(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
}

/** Whole days from today to the given date — negative when it has passed. */
export function daysUntil(iso: string): number {
    const target = startOfDay(new Date(`${iso}T00:00:00`));
    const today = startOfDay(new Date());

    return Math.round((target.getTime() - today.getTime()) / DAY);
}

export function formatDate(iso: string): string {
    return new Date(`${iso}T00:00:00`).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });
}

/**
 * How a due date should read and how urgent it looks. Overdue and today are
 * never carried by colour alone — the label says so too.
 */
export function dueMeta(iso: string | null): {
    text: string;
    label: string;
    className: string;
    days: number;
} {
    if (!iso) {
        return {
            text: 'No due date',
            label: '—',
            className: 'text-muted-foreground',
            days: Infinity,
        };
    }

    const days = daysUntil(iso);
    const label = formatDate(iso);

    if (days < 0) {
        return {
            text: `${label} · overdue`,
            label,
            className: 'text-danger-ink',
            days,
        };
    }
    if (days === 0) {
        return { text: 'Today', label, className: 'text-danger-ink', days };
    }
    if (days === 1) {
        return { text: 'Tomorrow', label, className: 'text-warning-ink', days };
    }
    if (days <= 4) {
        return { text: label, label, className: 'text-warning-ink', days };
    }

    return { text: label, label, className: 'text-muted-foreground', days };
}

/** "in 5d" / "3d late" / "Today" for the deadline panel's trailing label. */
export function countdown(iso: string): string {
    const days = daysUntil(iso);

    if (days === 0) return 'Today';
    if (days < 0) return `${Math.abs(days)}d late`;

    return `in ${days}d`;
}

/* -------------------------------------------------------------- activity */

/** Turns `task.completed` into the phrasing the feed reads with. */
export function activityPhrase(verb: string): string {
    return (
        {
            'task.created': 'created the task',
            'task.completed': 'completed',
            'task.assigned': 'assigned you',
            'task.moved': 'moved',
            'comment.created': 'commented on',
            'attachment.uploaded': 'uploaded a file to',
            'project.created': 'created the project',
        }[verb] ?? verb.replace('.', ' ')
    );
}

export const activityTone: Record<string, { icon: string; tone: Tone }> = {
    'task.created': { icon: 'plus', tone: 'brand' },
    'task.completed': { icon: 'check', tone: 'success' },
    'task.assigned': { icon: 'user-plus', tone: 'info' },
    'task.moved': { icon: 'columns-3', tone: 'warning' },
    'comment.created': { icon: 'message-square', tone: 'neutral' },
    'attachment.uploaded': { icon: 'paperclip', tone: 'neutral' },
    'project.created': { icon: 'folder', tone: 'brand' },
};
