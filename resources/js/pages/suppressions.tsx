import { DeliveryStatus } from '@/components/delivery-status';
import { Company, Metrics, Page, Pages } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { Link, useForm } from '@inertiajs/react';
import { ShieldBan, Upload } from 'lucide-react';

type Suppression = { id: number; email: string; reason: string; evidence: string | null; created_at: string };
type SuppressionImport = {
    id: number;
    source: string;
    status: string;
    total: number;
    processed: number;
    added: number;
    existing: number;
    rejected: number;
    created_at: string;
};

export default function Suppressions({
    company,
    suppressions,
    imports,
    total,
    canManage,
}: {
    company: Company;
    suppressions: Page<Suppression>;
    imports: SuppressionImport[];
    total: number;
    canManage: boolean;
}) {
    const base = `/organisations/${company.id}/suppressions`;
    const form = useForm({ file: null as File | null, source: 'Client historic global unsubscribe list', evidence: '' });

    return (
        <Workspace
            title="Global suppressions"
            subtitle={`${company.name} · These addresses are excluded from every campaign in this company, regardless of list.`}
        >
            <Metrics data={{ 'Suppressed addresses': total }} />
            {canManage && (
                <Panel
                    title="Import unsubscribe list"
                    description="Upload the client's existing opt-outs separately from contacts. This import creates suppression records only; it does not create contacts or add people to lists."
                >
                    <form
                        className="grid max-w-3xl gap-4 sm:grid-cols-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(`${base}/imports`);
                        }}
                    >
                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="suppression-file">CSV file</Label>
                            <Input
                                id="suppression-file"
                                type="file"
                                accept=".csv,.txt"
                                required
                                onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)}
                            />
                            <p className="text-muted-foreground text-sm">
                                Include a header row and one column containing email addresses. Up to 10,000 rows per file; larger files can be split.
                            </p>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="suppression-source">Source</Label>
                            <Input
                                id="suppression-source"
                                value={form.data.source}
                                onChange={(event) => form.setData('source', event.target.value)}
                                required
                                maxLength={120}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="suppression-evidence">Import note (optional)</Label>
                            <Input
                                id="suppression-evidence"
                                value={form.data.evidence}
                                onChange={(event) => form.setData('evidence', event.target.value)}
                                maxLength={1000}
                                placeholder="For example, exported from NewZapp on 25 November 2026"
                            />
                        </div>
                        <div className="sm:col-span-2">
                            <Errors errors={form.errors} />
                            <Button disabled={form.processing}>
                                <Upload aria-hidden="true" /> Upload and preview
                            </Button>
                        </div>
                    </form>
                </Panel>
            )}

            <Panel
                title="Recent imports"
                description="Imported addresses remain suppressed if they are later included in a contact CSV or another mailing list."
            >
                {imports.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr>
                                    {['Source', 'Status', 'Rows', 'Added', 'Already suppressed', 'Rejected'].map((heading) => (
                                        <th key={heading} className="p-3">
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {imports.map((item) => (
                                    <tr key={item.id} className="border-t">
                                        <td className="p-3">
                                            <Link className="font-medium underline" href={`${base}/imports/${item.id}`}>
                                                {item.source}
                                            </Link>
                                            <div className="text-muted-foreground text-xs">Import #{item.id}</div>
                                        </td>
                                        <td>
                                            <DeliveryStatus status={item.status} />
                                        </td>
                                        <td>
                                            {item.processed}/{item.total}
                                        </td>
                                        <td>{item.added}</td>
                                        <td>{item.existing}</td>
                                        <td>{item.rejected}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <p className="text-muted-foreground text-sm">No unsubscribe lists have been imported yet.</p>
                )}
            </Panel>

            <Panel
                title="Suppressed addresses"
                description="These addresses cannot receive campaigns from this company. Existing suppression reasons are retained when an address is encountered again."
            >
                {suppressions.data.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr>
                                    {['Email address', 'Reason', 'Source / evidence', 'Added'].map((heading) => (
                                        <th key={heading} className="p-3">
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {suppressions.data.map((item) => (
                                    <tr key={item.id} className="border-t">
                                        <td className="p-3 font-medium">{item.email}</td>
                                        <td className="capitalize">{item.reason.replaceAll('_', ' ')}</td>
                                        <td className="max-w-md truncate">{item.evidence ?? '—'}</td>
                                        <td>{new Date(item.created_at).toLocaleDateString()}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="rounded-lg border border-dashed p-8 text-center">
                        <ShieldBan className="text-muted-foreground mx-auto mb-2 h-6 w-6" />
                        <p className="font-medium">No suppressed addresses yet</p>
                        <p className="text-muted-foreground text-sm">Import the historic unsubscribe file here before importing active contacts.</p>
                    </div>
                )}
                <Pages page={suppressions} />
            </Panel>
        </Workspace>
    );
}
