import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { BarChart3, Building2, FileText, Fingerprint, LayoutGrid, List, Mail, Settings, ShieldCheck, Upload, Users } from 'lucide-react';
import AppLogo from './app-logo';
export function AppSidebar() {
    const { auth, organisations, currentOrganisationId } = usePage<SharedData>().props;
    const base = currentOrganisationId ? `/organisations/${currentOrganisationId}` : null;
    const workspace: NavItem[] = [
        { title: 'Overview', url: '/dashboard', icon: LayoutGrid },
        { title: 'Organisations', url: '/organisations', icon: Building2 },
    ];
    const audience: NavItem[] = base
        ? [
              { title: 'Contacts', url: base + '/contacts', icon: Users },
              { title: 'Lists & segments', url: base + '/audiences', icon: List },
              { title: 'Imports', url: base + '/imports', icon: Upload },
          ]
        : [];
    const campaigns: NavItem[] = base
        ? [
              { title: 'Campaigns', url: base + '/campaigns', icon: Mail },
              { title: 'Templates', url: base + '/templates', icon: FileText },
              { title: 'Reporting', url: base + '/reports', icon: BarChart3 },
          ]
        : [];
    const administration: NavItem[] = [
        ...(base
            ? [
                  { title: 'Sending identities', url: base + '/identities', icon: Fingerprint },
                  { title: 'Workspace settings', url: base, icon: Settings },
              ]
            : []),
        ...(auth.user.is_platform_admin ? [{ title: 'Platform administration', url: '/platform', icon: ShieldCheck }] : []),
    ];
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard">
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <div className="px-2 py-4 group-data-[collapsible=icon]:hidden">
                    <label htmlFor="workspace" className="text-muted-foreground mb-2 block text-xs font-medium">
                        WORKSPACE
                    </label>
                    <select
                        id="workspace"
                        className="bg-background w-full rounded-lg border p-2 text-sm"
                        value={currentOrganisationId ?? ''}
                        onChange={(e) => router.post(`/organisations/${e.target.value}/switch`)}
                    >
                        <option value="" disabled>
                            Select organisation
                        </option>
                        {organisations.map((o) => (
                            <option key={o.id} value={o.id}>
                                {o.name}
                            </option>
                        ))}
                    </select>
                </div>
            </SidebarHeader>
            <SidebarContent>
                <NavMain items={workspace} />
                <NavMain label="Audience" items={audience} />
                <NavMain label="Campaigns" items={campaigns} />
                <NavMain label="Settings" items={administration} />
            </SidebarContent>
            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
