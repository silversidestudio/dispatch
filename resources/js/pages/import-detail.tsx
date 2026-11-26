import { DeliveryStatus } from '@/components/delivery-status';
import { Company, Metrics, Select } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { Label } from '@/components/ui/label';
import { contactFieldLabel } from '@/lib/labels';
import { router, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { Import } from './imports';
export default function ImportDetail({
    company,
    import: job,
    sample,
    lists,
    selectedList,
    maxRows,
    rows,
    canManage,
}: {
    company: Company;
    import: Import;
    sample: { values: string[]; potential_duplicate: boolean; column_count_valid: boolean }[];
    lists: { id: number; name: string }[];
    selectedList: { id: number; name: string; archived: boolean } | null;
    maxRows: number;
    rows: { id: number; row_number: number; status: string; error: string | null }[];
    canManage: boolean;
}) {
    const base = `/organisations/${company.id}/imports/${job.id}`;
    const f = useForm<{ mapping: Record<string, number | null>; mailing_list_id: string; confirm_import: boolean }>({
        mapping: job.mapping ?? Object.fromEntries(['email', 'first_name', 'last_name', 'company_name', 'telephone'].map((k) => [k, job.headers.indexOf(k) >= 0 ? job.headers.indexOf(k) : null])),
        mailing_list_id: job.mailing_list_id ? String(job.mailing_list_id) : '',
        confirm_import: false,
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
            {['preview', 'failed'].includes(job.status) && canManage && (
                <Panel
                    title={job.status === 'preview' ? 'Review and confirm import' : 'Review and retry import'}
                    description={`Choose the mailing list and check the column mapping before the contacts are added. This file may contain up to ${maxRows.toLocaleString()} contact rows.`}
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
                                <Select key={k} label={`Map ${contactFieldLabel(k).toLowerCase()}`} value={f.data.mapping[k] === null ? '' : String(f.data.mapping[k])} change={(v) => f.setData('mapping', { ...f.data.mapping, [k]: v === '' ? null : Number(v) })}>
                                    <option value="">Do not import</option>
                                    {job.headers.map((h, i) => <option key={i} value={i}>{h}</option>)}
                                </Select>
                            ))}
                        </div>
                        {job.processed > 0 && selectedList ? (
                            <div className="space-y-1.5 text-sm">
                                <Label>Mailing list</Label>
                                <p className="rounded-lg border px-3 py-2.5">{selectedList.name}{selectedList.archived ? ' · Archived' : ''}</p>
                                {selectedList.archived && <p className="text-muted-foreground">Restore this list before retrying the import.</p>}
                            </div>
                        ) : (
                            <Select label="Add contacts to" value={f.data.mailing_list_id} change={(value) => f.setData('mailing_list_id', value)}>
                                <option value="">Choose a mailing list</option>
                                {lists.map((list) => <option key={list.id} value={list.id}>{list.name}</option>)}
                            </Select>
                        )}
                        <div className="rounded-lg border border-amber-500/40 bg-amber-500/5 p-4">
                            <p className="font-medium">This import cannot be undone as one action.</p>
                            <p className="text-muted-foreground mt-1 text-sm">New contacts will be created and added to the selected list. Existing contacts in the file will be added to the list without overwriting their details. Suppression records remain in force. Review the list and column mapping before continuing.</p>
                            <div className="mt-3 flex items-start gap-2">
                                <Checkbox id="confirm-import" checked={f.data.confirm_import} onCheckedChange={(checked) => f.setData('confirm_import', checked === true)} />
                                <Label htmlFor="confirm-import" className="cursor-pointer leading-5">I have checked the preview, column mapping and mailing list.</Label>
                            </div>
                        </div>
                        <Errors errors={f.errors} />
                        <Button disabled={f.processing || !f.data.confirm_import || !f.data.mailing_list_id}>{job.status === 'preview' ? 'Import contacts' : 'Retry remaining rows'}</Button>
                    </form>
                </Panel>
            )}
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
