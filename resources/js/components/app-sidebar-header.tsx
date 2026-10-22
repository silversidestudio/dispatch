import AppearanceDropdown from '@/components/appearance-dropdown';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem } from '@/types';
import { Link } from '@inertiajs/react';
import { Bell, Search } from 'lucide-react';
export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItem[] }) {
    return (
        <header className="flex h-16 shrink-0 items-center justify-between gap-2 border-b px-4 md:px-6">
            <div className="flex items-center gap-2">
                <SidebarTrigger />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="flex items-center gap-2">
                <Button variant="ghost" size="sm" asChild>
                    <Link href="/organisations">
                        <Search className="size-4" />
                        <span className="hidden md:inline">Find workspace</span>
                    </Link>
                </Button>
                <AppearanceDropdown />
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" aria-label="Notifications">
                            <Bell className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuLabel>No notifications yet</DropdownMenuLabel>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>
    );
}
