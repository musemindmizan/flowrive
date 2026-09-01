import { Link } from '@inertiajs/react';
import {
    Bell,
    Folder,
    LayoutGrid,
    Settings,
    SquareCheck,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { dashboard } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { index as projectsIndex } from '@/routes/projects';
import { index as tasksIndex } from '@/routes/tasks';
import { index as teamIndex } from '@/routes/team';
import { index as notificationsIndex } from '@/routes/notifications';

type Entry = {
    title: string;
    icon: LucideIcon;
    href?: string;
    /** Screens that ship in a later chunk — shown, but honestly inert. */
    soon?: boolean;
};

const workspaceNav: Entry[] = [
    { title: 'Dashboard', icon: LayoutGrid, href: dashboard().url },
    { title: 'Projects', icon: Folder, href: projectsIndex().url },
    { title: 'My Tasks', icon: SquareCheck, href: tasksIndex().url },
    { title: 'Team', icon: Users, href: teamIndex().url },
];

const personalNav: Entry[] = [
    { title: 'Notifications', icon: Bell, href: notificationsIndex().url },
    { title: 'Settings', icon: Settings, href: editProfile().url },
];

function NavGroup({ label, entries }: { label: string; entries: Entry[] }) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <SidebarMenu>
                {entries.map((entry) => (
                    <SidebarMenuItem key={entry.title}>
                        {entry.href ? (
                            <SidebarMenuButton
                                asChild
                                isActive={isCurrentUrl(entry.href)}
                                tooltip={{ children: entry.title }}
                            >
                                <Link href={entry.href} prefetch>
                                    <entry.icon />
                                    <span>{entry.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        ) : (
                            <SidebarMenuButton
                                disabled
                                tooltip={{
                                    children: `${entry.title} — not built yet`,
                                }}
                                className="cursor-default opacity-55"
                            >
                                <entry.icon />
                                <span>{entry.title}</span>
                                <span className="text-muted-foreground ml-auto text-[10px] font-medium tracking-wide uppercase">
                                    Soon
                                </span>
                            </SidebarMenuButton>
                        )}
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-4">
                <NavGroup label="Workspace" entries={workspaceNav} />
                <NavGroup label="Personal" entries={personalNav} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
