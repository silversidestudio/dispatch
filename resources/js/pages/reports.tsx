import { DeliveryStatus } from '@/components/delivery-status';
import { Company, Field } from '@/components/marketing';
import { Panel, Workspace } from '@/components/workspace';
import { modeLabel } from '@/lib/labels';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
type Report = {
    id: number;
    name: string;
    status: string;
    mode: string;
    date: string;
    audience_at_snapshot: number;
    eligible_at_snapshot: number;
    excluded_at_snapshot: number;
    simulated_attempts: number;
    simulated_acceptances: number;
    failed: number;
    suppressed_before_submission: number;
    retries: number;
};
export default function Reports({ company, reports }: { company: Company; reports: Report[] }) {
    const [start, setStart] = useState('');
    const [end, setEnd] = useState('');
    const filtered = reports.filter((r) => (!start || r.date.slice(0, 10) >= start) && (!end || r.date.slice(0, 10) <= end));
    return (
        <Workspace
            title="Campaign reports"
            subtitle="Campaign results are labelled as simulation or SendGrid activity. Acceptance by SendGrid is not the same as inbox placement."
        >
            <div>
                <Link className="text-sm underline" href={`/organisations/${company.id}/campaign-comparison`}>
                    Compare campaigns and provider analytics
                </Link>
            </div>
            <Panel title="Campaign results" description="Showing up to 30 campaigns. Dates are based on when each campaign was created.">
                <div className="mb-5 flex flex-wrap gap-4">
                    <Field label="From date" type="date" value={start} change={setStart} />
                    <Field label="To date" type="date" value={end} change={setEnd} />
                </div>
                <div className="overflow-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr>
                                {[
                                    'Campaign',
                                    'Status',
                                    'Mode',
                                    'Audience',
                                    'Eligible',
                                    'Excluded',
                                    'Attempts',
                                    'Accepted',
                                    'Failed',
                                    'Suppressed later',
                                    'Retries',
                                ].map((s) => (
                                    <th key={s} className="p-3">
                                        {s}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {filtered.map((r) => (
                                <tr key={r.id} className="border-t">
                                    <td className="p-3">
                                        <Link className="underline" href={`/organisations/${company.id}/campaigns/${r.id}`}>
                                            {r.name}
                                        </Link>
                                    </td>
                                    <td className="p-3">
                                        <DeliveryStatus status={r.status} />
                                    </td>
                                    <td className="p-3">{modeLabel(r.mode)}</td>
                                    {[
                                        r.audience_at_snapshot,
                                        r.eligible_at_snapshot,
                                        r.excluded_at_snapshot,
                                        r.simulated_attempts,
                                        r.simulated_acceptances,
                                        r.failed,
                                        r.suppressed_before_submission,
                                        r.retries,
                                    ].map((v, i) => (
                                        <td key={i} className="p-3">
                                            {v}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {!filtered.length && <p className="text-muted-foreground py-10 text-center">No campaigns in this date range.</p>}
            </Panel>
        </Workspace>
    );
}
