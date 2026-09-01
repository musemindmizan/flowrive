import { toneFill } from '@/lib/flowrive';
import { cn } from '@/lib/utils';
import type { Tone } from '@/types';

/**
 * A percentage bar that also announces its value, so the number is available
 * to a screen reader rather than only being implied by the fill width.
 */
export function ProgressBar({
    value,
    tone = 'brand',
    className,
    label,
}: {
    value: number;
    tone?: Tone;
    className?: string;
    label?: string;
}) {
    const clamped = Math.max(0, Math.min(100, value));

    return (
        <div
            className={cn(
                'bg-surface-3 h-1.5 overflow-hidden rounded-full',
                className,
            )}
            role="progressbar"
            aria-valuenow={clamped}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-label={label}
        >
            <div
                className={cn(
                    'h-full rounded-full transition-[width] duration-500 ease-out',
                    toneFill[value >= 100 ? 'success' : tone],
                )}
                style={{ width: `${clamped}%` }}
            />
        </div>
    );
}
