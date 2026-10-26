import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Link } from '@inertiajs/react';
import { ReactNode, useId } from 'react';
export type Company = { id: number; name: string; postal_address?: string | null; email_branding?: { button_color?: string } | null };
export type Contact = {
    id: number;
    email: string;
    first_name: string | null;
    last_name: string | null;
    company_name: string | null;
    telephone: string | null;
    source: string;
    status: string;
    eligibility: string;
    attributes: Record<string, string> | null;
    created_at: string;
    member?: boolean;
};
export type Page<T> = { data: T[]; prev_page_url: string | null; next_page_url: string | null; total: number; current_page: number };
export function Field({
    label,
    value,
    change,
    type = 'text',
    required = false,
    disabled = false,
}: {
    label: string;
    value: string;
    change: (s: string) => void;
    type?: string;
    required?: boolean;
    disabled?: boolean;
}) {
    const id = useId();
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}</Label>
            <Input id={id} type={type} required={required} disabled={disabled} value={value} onChange={(e) => change(e.target.value)} />
        </div>
    );
}
export function Select({ label, value, change, children }: { label: string; value: string; change: (s: string) => void; children: ReactNode }) {
    const id = useId();
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}</Label>
            <select
                id={id}
                className="bg-background h-10 w-full rounded-lg border px-3 text-sm"
                value={value}
                onChange={(e) => change(e.target.value)}
            >
                {children}
            </select>
        </div>
    );
}
export function Pages<T>({ page }: { page: Page<T> }) {
    return (
        <div className="mt-5 flex items-center justify-between text-sm">
            <span className="text-muted-foreground">
                {page.total} records · page {page.current_page}
            </span>
            <div className="flex gap-4">
                {page.prev_page_url && <Link href={page.prev_page_url}>Previous</Link>}
                {page.next_page_url && <Link href={page.next_page_url}>Next</Link>}
            </div>
        </div>
    );
}
export function Metrics({ data }: { data: Record<string, number> }) {
    return (
        <div className="grid gap-4 sm:grid-cols-3 xl:grid-cols-4">
            {Object.entries(data).map(([key, value]) => (
                <div key={key} className="bg-card rounded-xl border p-5">
                    <p className="text-muted-foreground text-sm capitalize">{key.replaceAll('_', ' ')}</p>
                    <p className="mt-3 text-3xl font-semibold">{value}</p>
                </div>
            ))}
        </div>
    );
}
