import { cn } from '@/lib/utils';

const tones = {
    online: 'bg-success',
    away: 'bg-warning',
    offline: 'bg-border-strong',
} as const;

/**
 * Presence is derived from last_seen_at rather than stored, so the label is
 * spelled out for screen readers rather than left to the colour.
 */
export function PresenceDot({
    presence,
    className,
}: {
    presence: keyof typeof tones;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'ring-card absolute -right-0.5 -bottom-0.5 size-2.5 rounded-full ring-2',
                tones[presence],
                className,
            )}
        >
            <span className="sr-only">{presence}</span>
        </span>
    );
}
