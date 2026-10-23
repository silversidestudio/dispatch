import { Company, Field, Page, Pages } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { Link, useForm } from '@inertiajs/react';
export type Import = {
    id: number;
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
export default function Imports({ company, imports, canManage }: { company: Company; imports: Page<Import>; canManage: boolean }) {
    const f = useForm({ file: null as File | null, source: 'CSV import' });
    const base = `/organisations/${company.id}/imports`;
    return (
        <Workspace
            title="Contact imports"
            subtitle="CSV imports preserve existing contacts and suppression. Imported addresses are never automatically opted in."
        >
            {canManage && (
                <Panel
                    title="Upload CSV"
                    description="Up to 5 MB, 10,000 data rows and 30 columns. UTF-8, comma separated, with a unique header row."
                >
                    <form
                        className="max-w-xl space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            f.post(base);
                        }}
                    >
                        <Label htmlFor="csv">CSV file</Label>
                        <Input id="csv" type="file" accept=".csv,.txt" required onChange={(e) => f.setData('file', e.target.files?.[0] ?? null)} />
                        <Field label="Contact source" required value={f.data.source} change={(v) => f.setData('source', v)} />
                        <Errors errors={f.errors} />
                        <Button disabled={f.processing}>Upload and preview</Button>
                    </form>
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
                                    <td>{i.status}</td>
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
