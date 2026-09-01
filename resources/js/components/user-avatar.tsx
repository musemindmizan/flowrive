import { avatarClass, initials } from '@/lib/flowrive';
import { cn } from '@/lib/utils';
import type { Person } from '@/types';

const sizes = {
    xs: 'size-6 text-[9px]',
    sm: 'size-7 text-[10px]',
    md: 'size-8 text-[11px]',
    lg: 'size-10 text-[13px]',
} as const;

type Size = keyof typeof sizes;

/**
 * Initials on a colour derived from the person's name. No photo yet — when
 * avatar_path lands this becomes an <img> with these as the fallback.
 */
export function UserAvatar({
    person,
    size = 'md',
    className,
}: {
    person: Person;
    size?: Size;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex flex-none items-center justify-center rounded-full font-semibold text-white select-none',
                sizes[size],
                avatarClass(person.name),
                className,
            )}
            title={person.name}
        >
            {initials(person.name)}
        </span>
    );
}

/** Overlapping stack with a +N chip once the list runs past `max`. */
export function AvatarGroup({
    people,
    max = 4,
    size = 'sm',
}: {
    people: Person[];
    max?: number;
    size?: Size;
}) {
    const shown = people.slice(0, max);
    const rest = people.length - shown.length;

    return (
        <span className="inline-flex items-center">
            {shown.map((person, index) => (
                <UserAvatar
                    key={person.id}
                    person={person}
                    size={size}
                    className={cn(
                        'ring-card ring-2',
                        index > 0 && '-ml-2',
                    )}
                />
            ))}
            {rest > 0 && (
                <span
                    className={cn(
                        'ring-card bg-surface-3 text-ink-2 -ml-2 inline-flex flex-none items-center justify-center rounded-full font-semibold ring-2',
                        sizes[size],
                    )}
                >
                    +{rest}
                </span>
            )}
        </span>
    );
}
