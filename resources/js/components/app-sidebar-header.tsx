import AppearanceDropdown from '@/components/appearance-dropdown';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Bell, CheckCheck, Search } from 'lucide-react';
import { SharedData } from '@/types';
export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItem[] }) {
    const { auth, organisations, notifications } = usePage<SharedData>().props;
    return (
        <header className="flex h-16 shrink-0 items-center justify-between gap-2 border-b px-4 md:px-6">
            <div className="flex items-center gap-2">
                <SidebarTrigger />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="flex items-center gap-2">
                {(auth.user.is_platform_admin || organisations.length !== 1) && (
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/organisations">
                            <Search className="size-4" />
                            <span className="hidden md:inline">Find workspace</span>
                        </Link>
                    </Button>
                )}
                <AppearanceDropdown />
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon" aria-label={notifications.unread_count ? `Notifications, ${notifications.unread_count} unread` : 'Notifications'} className="relative">
                            <Bell className="size-4" />
                            {notifications.unread_count > 0 && (
                                <span className="absolute top-1 right-1 size-2 rounded-full bg-primary" aria-hidden="true" />
                            )}
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="max-h-[min(70vh,28rem)] w-[min(22rem,calc(100vw-2rem))] overflow-y-auto">
                        <div className="flex items-center justify-between gap-3 px-2 py-1.5">
                            <DropdownMenuLabel className="p-0">Notifications</DropdownMenuLabel>
                            {notifications.unread_count > 0 && (
                                <DropdownMenuItem
                                    onSelect={(event) => {
                                        event.preventDefault();
                                        router.post('/notifications/read-all');
                                    }}
                                    className="h-auto p-1 text-xs"
                                >
                                    <CheckCheck aria-hidden="true" className="size-3.5" />
                                    Mark all read
                                </DropdownMenuItem>
                            )}
                        </div>
                        {notifications.items.length ? (
                            <>
                                <DropdownMenuSeparator />
                                {notifications.items.map((item) => (
                                    <DropdownMenuItem
                                        key={item.id}
                                        onSelect={(event) => {
                                            event.preventDefault();
                                            router.post(`/notifications/${item.id}/open`);
                                        }}
                                        className="items-start gap-2 whitespace-normal py-3"
                                    >
                                        <span className={`mt-1.5 size-2 shrink-0 rounded-full ${item.read ? 'bg-transparent' : 'bg-primary'}`} aria-hidden="true" />
                                        <span className="min-w-0">
                                            <span className="block font-medium">{item.title}</span>
                                            <span className="text-muted-foreground mt-1 block text-xs leading-relaxed">{item.body}</span>
                                            {item.created_at && (
                                                <time className="text-muted-foreground mt-1 block text-[11px]" dateTime={item.created_at}>
                                                    {new Date(item.created_at).toLocaleString()}
                                                </time>
                                            )}
                                        </span>
                                    </DropdownMenuItem>
                                ))}
                            </>
                        ) : (
                            <p className="text-muted-foreground px-2 py-4 text-sm">No notifications yet.</p>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>
    );
}
