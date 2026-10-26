import AppLayout from '@/layouts/app-layout';
import { SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { ReactNode, useEffect } from 'react';
import { toast } from 'sonner';
export function Workspace({ title, subtitle, children, actions }: { title: string; subtitle?: string; children: ReactNode; actions?: ReactNode }) {
    const { flash, errors } = usePage<SharedData>().props;
    useEffect(() => {
        if (flash.success) toast.success(flash.success);
    }, [flash.success]);
    return (
        <AppLayout breadcrumbs={[{ title, href: '#' }]}>
            <Head title={title} />
            <main className="mx-auto w-full max-w-[1500px] min-w-0 space-y-7 p-4 md:p-8">
                {errors && Object.keys(errors).length > 0 && (
                    <div role="alert" className="border-destructive/30 bg-destructive/5 text-destructive rounded-xl border p-4 text-sm">
                        {Object.values(errors).map((message, i) => (
                            <p key={i}>{String(message)}</p>
                        ))}
                    </div>
                )}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="max-w-full min-w-0">
                        <p className="text-muted-foreground mb-2 text-xs font-medium tracking-widest uppercase">Your email workspace</p>
                        <h1 className="text-2xl font-semibold tracking-tight break-words md:text-3xl">{title}</h1>
                        {subtitle && <p className="text-muted-foreground mt-2 text-sm">{subtitle}</p>}
                    </div>
                    {actions}
                </div>
                {children}
            </main>
        </AppLayout>
    );
}
export function Panel({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
    return (
        <section className="bg-card min-w-0 rounded-2xl border p-5 shadow-xs md:p-6">
            <h2 className="font-semibold tracking-tight">{title}</h2>
            {description && <p className="text-muted-foreground mt-1 text-sm">{description}</p>}
            <div className="mt-5">{children}</div>
        </section>
    );
}
export function Errors({ errors }: { errors: Record<string, string> }) {
    return (
        <div aria-live="polite">
            {Object.entries(errors).map(([key, value]) => (
                <p key={key} className="text-destructive my-2 text-sm">
                    {value}
                </p>
            ))}
        </div>
    );
}
