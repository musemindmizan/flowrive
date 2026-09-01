import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/**
 * Label + control + error, in one place so every form on the product spaces
 * and announces itself the same way.
 */
export function Field({
    label,
    htmlFor,
    error,
    hint,
    required,
    className,
    children,
}: {
    label: string;
    htmlFor?: string;
    error?: string;
    hint?: string;
    required?: boolean;
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <div className={className}>
            <Label htmlFor={htmlFor} className="mb-1.5 text-[13px]">
                {label}
                {required && (
                    <span className="text-danger" aria-hidden>
                        *
                    </span>
                )}
            </Label>
            {children}
            {error ? (
                <p className="text-danger-ink mt-1.5 text-xs" role="alert">
                    {error}
                </p>
            ) : hint ? (
                <p className="text-muted-foreground mt-1.5 text-xs">{hint}</p>
            ) : null}
        </div>
    );
}

/** A plain <select> styled to match the Input component. */
export function Select({
    className,
    ...props
}: React.ComponentProps<'select'>) {
    return (
        <select
            className={cn(
                'border-input bg-card text-foreground focus-visible:border-brand-400 focus-visible:ring-ring/40 h-9 w-full rounded-lg border px-2.5 text-[13px] shadow-xs transition outline-none focus-visible:ring-[3px] disabled:opacity-50',
                className,
            )}
            {...props}
        />
    );
}

export function Textarea({
    className,
    ...props
}: React.ComponentProps<'textarea'>) {
    return (
        <textarea
            className={cn(
                'border-input bg-card text-foreground placeholder:text-muted-foreground focus-visible:border-brand-400 focus-visible:ring-ring/40 w-full resize-y rounded-lg border px-3 py-2 text-[13px] shadow-xs transition outline-none focus-visible:ring-[3px]',
                className,
            )}
            {...props}
        />
    );
}

/** Multi-select rendered as toggle chips — better than a ctrl-click list. */
export function ChipPicker<T extends { id: number; name: string }>({
    options,
    selected,
    onToggle,
    empty = 'Nothing to choose from.',
}: {
    options: T[];
    selected: number[];
    onToggle: (id: number) => void;
    empty?: string;
}) {
    if (options.length === 0) {
        return <p className="text-muted-foreground text-xs">{empty}</p>;
    }

    return (
        <div className="flex flex-wrap gap-1.5">
            {options.map((option) => {
                const on = selected.includes(option.id);

                return (
                    <button
                        key={option.id}
                        type="button"
                        aria-pressed={on}
                        onClick={() => onToggle(option.id)}
                        className={cn(
                            'inline-flex h-7 items-center gap-1 rounded-full border px-2.5 text-[11px] font-medium transition',
                            on
                                ? 'border-brand-300 bg-brand-soft text-brand-ink'
                                : 'border-border text-muted-foreground hover:bg-secondary',
                        )}
                    >
                        {option.name}
                    </button>
                );
            })}
        </div>
    );
}
