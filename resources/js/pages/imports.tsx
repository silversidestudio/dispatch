import { DeliveryStatus } from '@/components/delivery-status';
import { Company, Field, Page, Pages, Select } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { Link, useForm } from '@inertiajs/react';
import { ShieldBan } from 'lucide-react';
export type Import = {
    id: number;
    mailing_list_id: number | null;
    status: string;
    source: string;
    total: number;
    processed: number;
    successful: number;
    rejected: number;
    headers: string[];
    mapping: Record<string, number | null> | null;
    failure: string | null;
};
type MailingListOption = { id: number; name: string };
export default function Imports({ company, imports, lists, maxRows, canManage }: { company: Company; imports: Page<Import>; lists: MailingListOption[]; maxRows: number; canManage: boolean }) {
    const f = useForm({ file: null as File | null, source: 'CSV import', mailing_list_id: '' });
    const base = `/organisations/${company.id}/imports`;
    return (
        <Workspace
            title="Contact imports"
            subtitle="CSV contacts can be included in campaigns. The import records where they came from, but cannot check whether they agreed to receive marketing. Suppressed addresses stay excluded."
            actions={<Button variant="outline" asChild><Link href={`/organisations/${company.id}/suppressions`}><ShieldBan aria-hidden="true" /> Suppressed addresses</Link></Button>}
        >
            {canManage && (
                <Panel
                    title="Upload CSV"
                    description={`CSV files can be up to 5 MB, with up to ${maxRows.toLocaleString()} contact rows and 30 columns. Include one header row with unique column names.`}
                >
                    {lists.length ? <form
                        className="max-w-xl space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            f.post(base);
                        }}
                    >
                        <Label htmlFor="csv">CSV file</Label>
                        <Input id="csv" type="file" accept=".csv,.txt" required onChange={(e) => f.setData('file', e.target.files?.[0] ?? null)} />
                        <Field label="Contact source" required value={f.data.source} change={(v) => f.setData('source', v)} />
                        <Select label="Add contacts to" value={f.data.mailing_list_id} change={(value) => f.setData('mailing_list_id', value)}>
                            <option value="">Choose a mailing list</option>
                            {lists.map((list) => <option key={list.id} value={list.id}>{list.name}</option>)}
                        </Select>
                        <Errors errors={f.errors} />
                        <Button disabled={f.processing || !f.data.mailing_list_id}>Upload and preview</Button>
                    </form> : <p className="text-muted-foreground text-sm">Create a mailing list before uploading contacts.</p>}
                </Panel>
            )}
            <Panel title="Import history">
                <div className="overflow-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr>
                                {['Import', 'Status', 'Processed', 'Imported', 'Rejected'].map((s) => (
                                    <th key={s} className="p-3">
                                        {s}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {imports.data.map((i) => (
                                <tr className="border-t" key={i.id}>
                                    <td className="p-3">
                                        <Link className="underline" href={`${base}/${i.id}`}>
                                            #{i.id} · {i.source}
                                        </Link>
                                    </td>
                                    <td>
                                        <DeliveryStatus status={i.status} />
                                    </td>
                                    <td>
                                        {i.processed}/{i.total}
                                    </td>
                                    <td>{i.successful}</td>
                                    <td>{i.rejected}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pages page={imports} />
            </Panel>
        </Workspace>
    );
}
