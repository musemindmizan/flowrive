import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Bell,
    Columns3,
    Moon,
    ShieldCheck,
    SquareCheck,
    Sun,
    Users,
    Zap,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { dashboard, login, register } from '@/routes';

const features = [
    {
        icon: Columns3,
        title: 'Boards that match how you work',
        body: 'Five stages out of the box. Drag a card and the change is saved before your finger leaves the trackpad.',
    },
    {
        icon: SquareCheck,
        title: 'Every task, one list',
        body: 'Filter by project, status, priority or assignee. Overdue work is never more than one tab away.',
    },
    {
        icon: Users,
        title: 'Roles for the whole team',
        body: 'Admins, managers and members each see exactly what they should — and nothing they should not.',
    },
    {
        icon: Bell,
        title: 'Told once, not five times',
        body: 'Per-event notification preferences, quiet hours and a daily digest instead of a full inbox.',
    },
];

export default function Welcome({ auth }: { auth?: { user?: unknown } }) {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';

    return (
        <>
            <Head title="FlowRive — project management, quietly" />

            <div className="bg-background text-foreground min-h-screen">
                <header className="mx-auto flex max-w-6xl items-center justify-between px-5 py-5 sm:px-8">
                    <span className="flex items-center gap-2.5">
                        <span className="bg-primary text-primary-foreground flex size-8 items-center justify-center rounded-lg">
                            <AppLogoIcon className="size-5 fill-current" />
                        </span>
                        <span className="text-[15px] font-semibold tracking-tight">
                            FlowRive
                        </span>
                    </span>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Toggle theme"
                            onClick={() =>
                                updateAppearance(isDark ? 'light' : 'dark')
                            }
                        >
                            {isDark ? (
                                <Sun className="size-4.5" aria-hidden />
                            ) : (
                                <Moon className="size-4.5" aria-hidden />
                            )}
                        </Button>

                        {auth?.user ? (
                            <Button size="sm" asChild>
                                <Link href={dashboard()}>
                                    Open the app
                                    <ArrowRight className="size-4" aria-hidden />
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button variant="ghost" size="sm" asChild>
                                    <Link href={login()}>Sign in</Link>
                                </Button>
                                <Button size="sm" asChild>
                                    <Link href={register()}>Get started</Link>
                                </Button>
                            </>
                        )}
                    </div>
                </header>

                {/* Hero */}
                <section className="mx-auto max-w-6xl px-5 pt-12 pb-16 sm:px-8 sm:pt-20">
                    <span className="bg-brand-soft text-brand-ink inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold">
                        <Zap className="size-3" aria-hidden />
                        Project management, quietly
                    </span>

                    <h1 className="mt-5 max-w-3xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                        Every project, task and conversation in one calm
                        workspace.
                    </h1>

                    <p className="text-ink-2 mt-5 max-w-xl text-base leading-7">
                        Boards that stay in sync, deadlines you can see coming,
                        and a team that always knows what's next. No dashboards
                        full of numbers nobody reads.
                    </p>

                    <div className="mt-8 flex flex-wrap items-center gap-3">
                        <Button size="lg" asChild>
                            <Link href={auth?.user ? dashboard() : register()}>
                                {auth?.user
                                    ? 'Open your workspace'
                                    : 'Start free'}
                                <ArrowRight className="size-4" aria-hidden />
                            </Link>
                        </Button>
                        {!auth?.user && (
                            <Button variant="outline" size="lg" asChild>
                                <Link href={login()}>Sign in</Link>
                            </Button>
                        )}
                    </div>

                    <p className="text-muted-foreground mt-4 flex items-center gap-1.5 text-xs">
                        <ShieldCheck className="size-3.5" aria-hidden />
                        Two-factor authentication and passkeys supported from
                        day one.
                    </p>
                </section>

                {/* Features */}
                <section className="border-border bg-card border-y">
                    <div className="mx-auto grid max-w-6xl gap-8 px-5 py-14 sm:grid-cols-2 sm:px-8">
                        {features.map((feature) => (
                            <div key={feature.title} className="flex gap-4">
                                <span className="bg-brand-soft text-brand-ink flex size-10 flex-none items-center justify-center rounded-xl">
                                    <feature.icon
                                        className="size-5"
                                        aria-hidden
                                    />
                                </span>
                                <div>
                                    <h2 className="text-[15px] font-semibold tracking-tight">
                                        {feature.title}
                                    </h2>
                                    <p className="text-ink-2 mt-1.5 text-[13px] leading-6">
                                        {feature.body}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                <footer className="text-muted-foreground mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-5 py-8 text-xs sm:px-8">
                    <p>© {new Date().getFullYear()} FlowRive</p>
                    <p>Built with Laravel, Inertia and React.</p>
                </footer>
            </div>
        </>
    );
}
