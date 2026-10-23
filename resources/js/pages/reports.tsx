import { Company, Field } from '@/components/marketing';
import { Panel, Workspace } from '@/components/workspace';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
type Report = {
    id: number;
    name: string;
    status: string;
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
            title="Simulation reports"
            subtitle="Actual stored simulation results. Acceptance does not establish inbox delivery, opens or clicks."
        >
            <Panel title="Campaign results" description="Latest 30 snapshotted campaigns. Date filters use the campaign creation date.">
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
                                    {[
                                        r.status,
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
                {!filtered.length && <p className="text-muted-foreground py-10 text-center">No simulated campaigns in this date range.</p>}
            </Panel>
        </Workspace>
    );
}
