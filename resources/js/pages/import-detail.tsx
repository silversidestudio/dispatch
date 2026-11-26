import { DeliveryStatus } from '@/components/delivery-status';
import { Company, Metrics, Select } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { contactFieldLabel } from '@/lib/labels';
import { router, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { Import } from './imports';
export default function ImportDetail({
    company,
    import: job,
    sample,
    rows,
    canManage,
}: {
    company: Company;
    import: Import;
    sample: { values: string[]; potential_duplicate: boolean; column_count_valid: boolean }[];
    rows: { id: number; row_number: number; status: string; error: string | null }[];
    canManage: boolean;
}) {
    const base = `/organisations/${company.id}/imports/${job.id}`;
    const f = useForm({
        mapping:
            job.mapping ??
            Object.fromEntries(
                ['email', 'first_name', 'last_name', 'company_name', 'telephone'].map((k) => [
                    k,
                    job.headers.indexOf(k) >= 0 ? job.headers.indexOf(k) : null,
                ]),
            ),
    });
    useEffect(() => {
        if (!['queued', 'processing'].includes(job.status)) return;
        const timer = setInterval(() => router.reload({ only: ['import', 'rows'] }), 2500);
        return () => clearInterval(timer);
    }, [job.status]);
    return (
        <Workspace title={`Import #${job.id}`} subtitle={job.source} actions={<DeliveryStatus status={job.status} />}>
            <Metrics data={{ total: job.total, processed: job.processed, imported: job.successful, rejected: job.rejected }} />
            {job.failure && (
                <p role="alert" className="text-destructive">
                    {job.failure}
                </p>
            )}
            {job.status === 'preview' && canManage && (
                <Panel
                    title="Map columns"
                    description="Match each CSV column to a contact field. New contacts can be included in campaigns. Existing contacts and suppression records are kept."
                >
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            f.post(base + '/start');
                        }}
                    >
                        <div className="grid gap-4 md:grid-cols-3">
                            {Object.keys(f.data.mapping).map((k) => (
                                <Select
                                    key={k}
                                    label={`Map ${contactFieldLabel(k).toLowerCase()}`}
                                    value={f.data.mapping[k] === null ? '' : String(f.data.mapping[k])}
                                    change={(v) => f.setData('mapping', { ...f.data.mapping, [k]: v === '' ? null : Number(v) })}
                                >
                                    <option value="">Do not import</option>
                                    {job.headers.map((h, i) => (
                                        <option key={i} value={i}>
                                            {h}
                                        </option>
                                    ))}
                                </Select>
                            ))}
                        </div>
                        <Errors errors={f.errors} />
                            <Button disabled={f.processing}>Import contacts</Button>
                    </form>
                </Panel>
            )}
            {job.status === 'failed' && canManage && <Button onClick={() => router.post(base + '/start')}>Retry remaining rows</Button>}
            <Panel
                title="Preview"
                description="Preview of the first 10 rows. Possible duplicates are flagged here; all rows are checked during import."
            >
                <div className="overflow-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr>
                                {job.headers.map((h, i) => (
                                    <th className="p-2" key={i}>
                                        {h}
                                    </th>
                                ))}
                                <th>Check</th>
                            </tr>
                        </thead>
                        <tbody>
                            {sample.map((s, i) => (
                                <tr className="border-t" key={i}>
                                    {s.values.map((v, j) => (
                                        <td className="p-2" key={j}>
                                            {v}
                                        </td>
                                    ))}
                                    <td>
                                        {!s.column_count_valid
                                            ? 'Column mismatch'
                                            : f.data.mapping.email === null
                                              ? 'Choose email column'
                                              : !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s.values[f.data.mapping.email] ?? '')
                                                ? 'Invalid mapped email'
                                                : sample
                                                        .slice(0, i)
                                                        .some(
                                                            (row) =>
                                                                (row.values[f.data.mapping.email!] ?? '').toLowerCase() ===
                                                                (s.values[f.data.mapping.email!] ?? '').toLowerCase(),
                                                        )
                                                  ? 'Duplicate in preview'
                                                  : s.potential_duplicate
                                                    ? 'Possible duplicate'
                                                    : 'Ready for full check'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Panel>
            <Panel title="Recent row results">
                {!rows.length && <p className="text-muted-foreground mb-3 text-sm">Import row results will appear here.</p>}
                <Button asChild variant="outline">
                    <a href={base + '/errors'}>Download rejected rows</a>
                </Button>
                <ul className="mt-4 space-y-2 text-sm">
                    {rows.map((r) => (
                        <li key={r.id}>
                            Row {r.row_number}: <DeliveryStatus status={r.status} />
                            {r.error ? ' · ' + r.error : ''}
                        </li>
                    ))}
                </ul>
            </Panel>
        </Workspace>
    );
}
