import { ChevronDown, ChevronUp, Minus, TriangleAlert } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { toneClasses, toneFill } from '@/lib/flowrive';
import { cn } from '@/lib/utils';
import type { Tone } from '@/types';

/**
 * Status is never carried by colour alone — every badge states its label, and
 * priority adds a glyph on top of the tone.
 */
export function StatusBadge({
    label,
    tone,
    className,
}: {
    label: string;
    tone: Tone;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex h-5.5 items-center gap-1.5 rounded-full px-2 text-[11px] font-medium whitespace-nowrap',
                toneClasses[tone],
                className,
            )}
        >
            <span
                className={cn('size-1.5 rounded-full', toneFill[tone])}
                aria-hidden
            />
            {label}
        </span>
    );
}

const priorityIcons: Record<string, LucideIcon> = {
    'chevron-down': ChevronDown,
    minus: Minus,
    'chevron-up': ChevronUp,
    'triangle-alert': TriangleAlert,
};

export function PriorityBadge({
    label,
    tone,
    icon,
    className,
}: {
    label: string;
    tone: Tone;
    icon: string;
    className?: string;
}) {
    const Icon = priorityIcons[icon] ?? Minus;

    return (
        <span
            className={cn(
                'inline-flex h-5.5 items-center gap-1 rounded-full px-2 text-[11px] font-medium whitespace-nowrap',
                toneClasses[tone],
                className,
            )}
        >
            <Icon className="size-3" aria-hidden />
            {label}
        </span>
    );
}

/** Neutral chip for counts and secondary metadata. */
export function Chip({
    children,
    className,
}: {
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'bg-neutral-soft text-neutral-ink inline-flex h-5.5 items-center gap-1 rounded-full px-2 text-[11px] font-medium whitespace-nowrap',
                className,
            )}
        >
            {children}
        </span>
    );
}
