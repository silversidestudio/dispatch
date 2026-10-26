import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { BarChart3, Building2, FileText, Fingerprint, LayoutGrid, List, Mail, Settings, ShieldCheck, Upload, Users } from 'lucide-react';
import AppLogo from './app-logo';
export function AppSidebar() {
    const { auth, organisations, currentOrganisationId } = usePage<SharedData>().props;
    const items: NavItem[] = [
        { title: 'Overview', url: '/dashboard', icon: LayoutGrid },
        { title: 'Organisations', url: '/organisations', icon: Building2 },
        ...(currentOrganisationId
            ? [
                  { title: 'Contacts', url: `/organisations/${currentOrganisationId}/contacts`, icon: Users },
                  { title: 'Lists & segments', url: `/organisations/${currentOrganisationId}/audiences`, icon: List },
                  { title: 'Imports', url: `/organisations/${currentOrganisationId}/imports`, icon: Upload },
                  { title: 'Campaigns', url: `/organisations/${currentOrganisationId}/campaigns`, icon: Mail },
                  { title: 'Templates', url: `/organisations/${currentOrganisationId}/templates`, icon: FileText },
                  { title: 'Sending identities', url: `/organisations/${currentOrganisationId}/identities`, icon: Fingerprint },
                  { title: 'Reporting', url: `/organisations/${currentOrganisationId}/reports`, icon: BarChart3 },
              ]
            : []),
        ...(currentOrganisationId ? [{ title: 'Workspace settings', url: `/organisations/${currentOrganisationId}`, icon: Settings }] : []),
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
                <NavMain items={items} />
                <div className="bg-background mx-4 mt-auto mb-4 rounded-xl border p-4 text-xs group-data-[collapsible=icon]:hidden">
                    <div className="mb-2 font-medium">Campaign safety</div>
                    <p className="text-muted-foreground leading-5">Review the delivery mode, audience and pre-flight checks before scheduling.</p>
                    <span className="mt-3 inline-flex rounded-full bg-indigo-500/10 px-2 py-1 text-indigo-600 dark:text-indigo-300">
                        Explicit sending controls
                    </span>
                </div>
            </SidebarContent>
            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
