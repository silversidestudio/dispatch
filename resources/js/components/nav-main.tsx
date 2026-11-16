import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';

export function NavMain({ items = [], label = 'Workspace' }: { items: NavItem[]; label?: string }) {
    const page = usePage();
    if (!items.length) return null;
    const path = page.url.split('?', 1)[0];
    const active = items
        .filter(
            (item) =>
                typeof item.url === 'string' &&
                (path === item.url || (!/^\/organisations(?:\/\d+)?$/.test(item.url) && path.startsWith(item.url + '/'))),
        )
        .sort((a, b) => String(b.url).length - String(a.url).length)[0];
    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton asChild isActive={item === active} tooltip={item.title}>
                            <Link href={item.url} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
