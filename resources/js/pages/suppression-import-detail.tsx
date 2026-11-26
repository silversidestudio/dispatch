import { DeliveryStatus } from '@/components/delivery-status';
import { Company, Metrics } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { Link, router, useForm } from '@inertiajs/react';
import { Download, ShieldBan } from 'lucide-react';
import { useEffect } from 'react';

type Import = {
    id: number;
    status: string;
    source: string;
    evidence: string | null;
    total: number;
    processed: number;
    added: number;
    existing: number;
    rejected: number;
    headers: string[];
    email_column: number | null;
    failure: string | null;
};

export default function SuppressionImportDetail({
    company,
    import: job,
    sample,
    rows,
    canManage,
}: {
    company: Company;
    import: Import;
    sample: string[][];
    rows: { id: number; row_number: number; status: string; error: string | null }[];
    canManage: boolean;
}) {
    const base = `/organisations/${company.id}/suppressions`;
    const guessed = job.headers.findIndex((header) => /e-?mail/i.test(header));
    const form = useForm({ email_column: job.email_column ?? Math.max(0, guessed) });
    useEffect(() => {
        if (['queued', 'processing'].includes(job.status)) {
            const timer = setInterval(() => router.reload({ only: ['import', 'rows'] }), 2500);
            return () => clearInterval(timer);
        }
    }, [job.status]);

    return (
        <Workspace
            title="Review unsubscribe import"
            subtitle={`${company.name} · Import #${job.id} · ${job.source}`}
            actions={
                <Button variant="outline" asChild>
                    <Link href={base}>Suppressed addresses</Link>
                </Button>
            }
        >
            <Metrics
                data={{
                    'Rows processed': job.processed,
                    'Added to suppression list': job.added,
                    'Already suppressed': job.existing,
                    'Rows needing review': job.rejected,
                }}
            />
            {job.failure && (
                <div role="alert" className="border-destructive/40 bg-destructive/5 rounded-lg border p-4 text-sm">
                    {job.failure}
                </div>
            )}
            {['preview', 'failed'].includes(job.status) && (
                <Panel
                    title="Choose the email column"
                    description="The addresses in this column will be excluded from every campaign in this workspace. No contact records will be added."
                >
                    {canManage && (
                        <form
                            className="space-y-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.post(`${base}/imports/${job.id}/start`);
                            }}
                        >
                            <label className="grid max-w-sm gap-2 text-sm font-medium">
                                Email address column
                                <select
                                    className="bg-background h-10 rounded-md border px-3 font-normal"
                                    value={form.data.email_column}
                                    onChange={(event) => form.setData('email_column', Number(event.target.value))}
                                >
                                    {job.headers.map((header, index) => (
                                        <option key={`${header}-${index}`} value={index}>
                                            {header}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <Errors errors={form.errors} />
                            <Button disabled={form.processing}>
                                <ShieldBan aria-hidden="true" /> {job.status === 'failed' ? 'Retry remaining rows' : 'Import as global suppressions'}
                            </Button>
                        </form>
                    )}
                    <div className="mt-6 overflow-x-auto">
                        <p className="mb-2 text-sm font-medium">Preview · first {sample.length} rows</p>
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr>
                                    {job.headers.map((header, index) => (
                                        <th
                                            key={`${header}-${index}`}
                                            className={`p-2 ${form.data.email_column === index ? 'bg-primary/10 text-primary' : ''}`}
                                        >
                                            {header}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {sample.map((row, rowIndex) => (
                                    <tr key={rowIndex} className="border-t">
                                        {job.headers.map((_, index) => (
                                            <td
                                                key={index}
                                                className={`max-w-60 truncate p-2 ${form.data.email_column === index ? 'bg-primary/5 font-medium' : ''}`}
                                            >
                                                {row[index] ?? ''}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Panel>
            )}
            {job.status !== 'preview' && (
                <Panel
                    title="Import progress"
                    description="Each address is excluded from every campaign in this workspace. Existing suppression details are kept."
                >
                    <div className="mb-4 flex items-center gap-3">
                        <DeliveryStatus status={job.status} />
                        <span className="text-muted-foreground text-sm">
                            {job.processed} of {job.total} rows processed
                        </span>
                    </div>
                    {job.rejected > 0 && (
                        <Button variant="outline" asChild>
                            <a href={`${base}/imports/${job.id}/errors`}>
                                <Download aria-hidden="true" /> Download row errors
                            </a>
                        </Button>
                    )}
                    {job.status === 'completed' && (
                        <p className="mt-4 text-sm">
                            These addresses are now excluded from all campaigns in this workspace, including campaigns using other mailing lists.
                        </p>
                    )}
                    {rows.length > 0 && (
                        <div className="mt-6 overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr>
                                        {['CSV row', 'Result', 'Details'].map((heading) => (
                                            <th key={heading} className="p-3">
                                                {heading}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((row) => (
                                        <tr key={row.id} className="border-t">
                                            <td className="p-3">{row.row_number}</td>
                                            <td>
                                                <DeliveryStatus status={row.status} />
                                            </td>
                                            <td>{row.error ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Panel>
            )}
        </Workspace>
    );
}
