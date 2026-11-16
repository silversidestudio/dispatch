import { Button } from '@/components/ui/button';
import type { SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
export default function Unsubscribe({ action, done }: { action: string; done: boolean }) {
    const { name } = usePage<SharedData>().props;
    return (
        <main className="bg-background flex min-h-screen items-center justify-center p-6">
            <Head title="Unsubscribe" />
            <section className="bg-card max-w-lg rounded-2xl border p-8">
                <p className="text-muted-foreground mb-4 text-xs tracking-widest uppercase">{name}</p>
                <h1 className="text-2xl font-semibold">{done ? 'You are unsubscribed' : 'Unsubscribe from marketing'}</h1>
                <p className="text-muted-foreground my-5 text-sm">
                    {done
                        ? 'Your preference has been recorded for this company across all its mailing lists.'
                        : 'Confirm to stop marketing from this company. Your preference will apply even if you remain on a mailing list.'}
                </p>
                {!done && <Button onClick={() => router.post(action)}>Confirm unsubscribe</Button>}
            </section>
        </main>
    );
}
