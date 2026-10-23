import { Company, Field, Metrics, Page, Pages } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { SharedData } from '@/types';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Campaign } from './campaigns';
export default function Detail({
    company,
    campaign,
    preflight,
    stats,
    recipients,
    events,
    history,
    canManage,
}: {
    company: Company;
    campaign: Campaign;
    preflight: { errors: string[]; total: number; eligible: number; excluded: number };
    stats: Record<string, number>;
    recipients: Page<{ id: number; email: string; status: string; attempt_count: number }>;
    events: { id: number; type: string; created_at: string }[];
    history: { id: number; action: string; created_at: string }[];
    canManage: boolean;
}) {
    const base = `/organisations/${company.id}/campaigns/${campaign.id}`;
    const f = useForm({ scheduled_at: '' });
    const [mobile, setMobile] = useState(false);
    const [link, setLink] = useState('');
    const { errors } = usePage<SharedData>().props;
    useEffect(() => {
        if (!['scheduled', 'processing'].includes(campaign.status)) return;
        const t = setInterval(() => router.reload({ only: ['campaign', 'stats', 'recipients', 'events', 'history'] }), 2500);
        return () => clearInterval(t);
    }, [campaign.status]);
    return (
        <Workspace
            title={campaign.name}
            subtitle={`${campaign.status.toUpperCase()} · Local simulation only · ${campaign.subject}`}
            actions={
                canManage && (
                    <div className="flex flex-wrap gap-2">
                        {campaign.status === 'draft' && (
                            <Button variant="outline" asChild>
                                <Link href={base + '/edit'}>Edit draft</Link>
                            </Button>
                        )}
                        <Button variant="outline" onClick={() => router.post(base + '/duplicate')}>
                            Duplicate
                        </Button>
                        {['draft', 'ready', 'scheduled', 'processing'].includes(campaign.status) && (
                            <Button
                                variant="outline"
                                onClick={() => {
                                    if (confirm('Cancel this campaign? Recorded attempts will remain.')) router.post(base + '/cancel');
                                }}
                            >
                                Cancel campaign
                            </Button>
                        )}
                    </div>
                )
            }
        >
            <Errors errors={errors as Record<string, string>} />
            <Metrics data={stats} />
            {stats.eligible_at_snapshot > 0 && (
                <Panel
                    title="Simulated recipient outcomes"
                    description="Counts use unique snapshotted recipients; retries do not increase the recipient denominator."
                >
                    <div className="space-y-4">
                        {[
                            ['Simulated accepted', stats.simulated_acceptances],
                            ['Failed', stats.failed],
                            ['Suppressed before submission', stats.suppressed_before_submission],
                            ['Pending or deferred', stats.pending_or_deferred],
                        ].map(([label, value]) => (
                            <div key={label} className="text-sm">
                                <div className="mb-2 flex justify-between">
                                    <span>{label}</span>
                                    <span>
                                        {value} / {stats.eligible_at_snapshot}
                                    </span>
                                </div>
                                <progress
                                    aria-label={String(label)}
                                    className="h-2 w-full accent-indigo-600"
                                    value={Number(value)}
                                    max={stats.eligible_at_snapshot}
                                />
                            </div>
                        ))}
                    </div>
                </Panel>
            )}
            {!campaign.snapshot && (
                <Panel
                    title="Pre-flight checks"
                    description={`${preflight.total} audience contacts · ${preflight.eligible} eligible · ${preflight.excluded} excluded`}
                >
                    {preflight.errors.length ? (
                        <ul className="text-destructive space-y-2 text-sm">
                            {preflight.errors.map((e) => (
                                <li key={e}>{e}</li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-emerald-600 dark:text-emerald-400">
                            Pre-flight checks pass for local simulation. Sender identity remains unverified.
                        </p>
                    )}
                    {canManage && campaign.status === 'draft' && (
                        <Button className="mt-4" disabled={!!preflight.errors.length} onClick={() => router.post(base + '/ready')}>
                            Mark ready
                        </Button>
                    )}
                    {canManage && campaign.status === 'ready' && (
                        <form
                            className="mt-5 flex flex-wrap items-end gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                const utc = f.data.scheduled_at ? new Date(f.data.scheduled_at).toISOString() : '';
                                router.post(base + '/schedule', { scheduled_at: utc });
                            }}
                        >
                            <Field
                                label="Schedule in your local time (optional)"
                                type="datetime-local"
                                value={f.data.scheduled_at}
                                change={(v) => f.setData('scheduled_at', v)}
                            />
                            <Button>Schedule simulation</Button>
                            <p className="text-muted-foreground w-full text-xs">
                                Leave blank to initiate now. Scheduling freezes the audience and email content.
                            </p>
                        </form>
                    )}
                </Panel>
            )}
            <Panel title="Email preview" description="Saved content, sample personalisation. No test email is transmitted.">
                <Button variant="outline" onClick={() => setMobile(!mobile)}>
                    {mobile ? 'Desktop preview' : 'Mobile preview'}
                </Button>
                <iframe
                    title="Campaign preview"
                    sandbox=""
                    referrerPolicy="no-referrer"
                    src={base + '/preview'}
                    className={`mx-auto mt-5 h-[600px] max-w-full border bg-white ${mobile ? 'w-[375px]' : 'w-full'}`}
                />
            </Panel>
            <Panel title="Recipient snapshot" description="Accepted means simulated server acceptance, never inbox placement.">
                <div className="overflow-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr>
                                {['Recipient', 'Status', 'Attempts', 'Unsubscribe test'].map((s) => (
                                    <th key={s} className="p-3">
                                        {s}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {recipients.data.map((r) => (
                                <tr key={r.id} className="border-t">
                                    <td className="p-3">{r.email}</td>
                                    <td>{r.status}</td>
                                    <td>{r.attempt_count}</td>
                                    <td>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                fetch(`${base}/recipients/${r.id}/unsubscribe`, { headers: { Accept: 'application/json' } })
                                                    .then((r) => r.json())
                                                    .then((d) => setLink(d.url))
                                            }
                                        >
                                            Generate local link
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pages page={recipients} />
                {link && (
                    <p className="mt-4 text-sm break-all">
                        <a className="underline" href={link} target="_blank" rel="noreferrer">
                            Open unsubscribe confirmation
                        </a>
                    </p>
                )}
            </Panel>
            <div className="grid gap-6 lg:grid-cols-2">
                <Panel title="Simulated delivery events">
                    {events.map((e) => (
                        <p className="border-b py-3 text-sm" key={e.id}>
                            Simulated {e.type} · {new Date(e.created_at).toLocaleString()}
                        </p>
                    ))}
                    {!events.length && <p className="text-muted-foreground text-sm">No delivery events yet.</p>}
                </Panel>
                <Panel title="Campaign state history">
                    {history.map((h) => (
                        <p className="border-b py-3 text-sm" key={h.id}>
                            {h.action} · {new Date(h.created_at).toLocaleString()}
                        </p>
                    ))}
                </Panel>
            </div>
        </Workspace>
    );
}
