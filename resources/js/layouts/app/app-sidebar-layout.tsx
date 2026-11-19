import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { useAppearance } from '@/hooks/use-appearance';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useLayoutEffect } from 'react';
import { Toaster } from 'sonner';

function WorkspaceTheme() {
    const { workspaceBranding } = usePage<SharedData>().props;
    const primaryColor = workspaceBranding?.primary_color;

    useLayoutEffect(() => {
        const root = document.documentElement;
        const clearWorkspaceTheme = () => {
            root.style.removeProperty('--primary');
            root.style.removeProperty('--primary-foreground');
            root.style.removeProperty('--ring');
            root.style.removeProperty('--sidebar-primary');
            root.style.removeProperty('--sidebar-primary-foreground');
            root.style.removeProperty('--sidebar-ring');
        };

        if (!primaryColor || !/^#[0-9a-fA-F]{6}$/.test(primaryColor)) {
            clearWorkspaceTheme();
            return;
        }

        const hex = primaryColor.slice(1);
        const channels = [0, 2, 4].map((offset) => parseInt(hex.slice(offset, offset + 2), 16) / 255);
        const linear = channels.map((channel) => (channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4));
        const luminance = linear[0] * 0.2126 + linear[1] * 0.7152 + linear[2] * 0.0722;
        const foreground = luminance > 0.179 ? '#111827' : '#ffffff';

        root.style.setProperty('--primary', primaryColor);
        root.style.setProperty('--primary-foreground', foreground);
        root.style.setProperty('--ring', primaryColor);
        root.style.setProperty('--sidebar-primary', primaryColor);
        root.style.setProperty('--sidebar-primary-foreground', foreground);
        root.style.setProperty('--sidebar-ring', primaryColor);

        return clearWorkspaceTheme;
    }, [primaryColor]);

    return null;
}

export default function AppSidebarLayout({ children, breadcrumbs = [] }: { children: React.ReactNode; breadcrumbs?: BreadcrumbItem[] }) {
    const { appearance } = useAppearance();
    return (
        <AppShell variant="sidebar">
            <WorkspaceTheme />
            <AppSidebar />
            <Toaster theme={appearance} richColors position="top-right" />
            <AppContent variant="sidebar">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
                <footer className="text-muted-foreground mt-auto border-t px-6 py-3 text-center text-xs">
                    by Silverside Web Solutions
                </footer>
            </AppContent>
        </AppShell>
    );
}
