import { EmailPreview } from '@/components/email-preview';
import { Company, Field, Metrics, Page, Pages, Select } from '@/components/marketing';
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
    preflight: {
        sender: string;
        pilot?: { enabled: boolean; daily_limit: number };
        sender_name: string;
        sender_email: string;
        postal_address: string | null;
        tracking: { open: boolean; click: boolean };
        errors: string[];
        total: number;
        eligible: number;
        excluded: number;
    };
    stats: Record<string, number>;
    recipients: Page<{ id: number; email: string; status: string; attempt_count: number }>;
    events: { id: number; type: string; created_at: string; simulated: boolean }[];
    history: { id: number; action: string; created_at: string }[];
    canManage: boolean;
}) {
    const base = `/organisations/${company.id}/campaigns/${campaign.id}`;
    const f = useForm({ scheduled_at: '', confirmation: '' });
    const test = useForm({ email: '', mode: 'simulation', confirmation: 'PREPARE' });
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
            subtitle={`${campaign.status.toUpperCase()} · ${campaign.delivery_mode === 'simulation' ? 'Local simulation only' : campaign.delivery_mode} · ${campaign.subject}`}
            actions={
                canManage && (
                    <div className="flex flex-wrap gap-2">
                        {campaign.status === 'draft' && (
                            <Button variant="outline" asChild>
                                <Link href={base + '/edit'}>Edit draft</Link>
                            </Button>
                        )}
                        <Button variant="outline" asChild>
                            <Link href={base + '/analytics'}>Campaign analytics</Link>
                        </Button>
                        {['scheduled', 'processing'].includes(campaign.status) && (
                            <Button variant="outline" onClick={() => router.post(base + '/pause')}>
                                Pause
                            </Button>
                        )}
                        {campaign.status === 'paused' && (
                            <Button variant="outline" onClick={() => router.post(base + '/resume')}>
                                Resume
                            </Button>
                        )}
                        <Button variant="outline" onClick={() => router.post(base + '/duplicate')}>
                            Duplicate
                        </Button>
                        {['draft', 'ready', 'scheduled', 'processing', 'paused'].includes(campaign.status) && (
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
            {canManage && (
                <Panel
                    title="Single-recipient test"
                    description="Prepare a separate test draft for an existing eligible contact. Simulation stays local; sandbox requires configured SendGrid and does not deliver. Nothing is submitted until the separate review and scheduling confirmation."
                >
                    <form
                        className="grid items-end gap-3 md:grid-cols-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            test.post(base + '/prepare-test');
                        }}
                    >
                        <Field label="Test recipient email" type="email" value={test.data.email} change={(v) => test.setData('email', v)} />
                        <Select label="Test mode" value={test.data.mode} change={(v) => test.setData('mode', v)}>
                            <option value="simulation">Local simulation</option>
                            <option value="sendgrid_sandbox">SendGrid sandbox validation</option>
                        </Select>
                        <Button disabled={test.processing}>Prepare test draft</Button>
                        <Errors errors={test.errors} />
                    </form>
                </Panel>
            )}
            <div className="text-muted-foreground flex flex-wrap items-center justify-between gap-3 text-sm">
                <p>Save your draft → preview the email → review recipients → schedule</p>
                <Button asChild size="sm" variant="outline">
                    <a href="#email-preview">Review email preview</a>
                </Button>
            </div>
            <Panel title="Final campaign review">
                <dl className="grid gap-4 text-sm md:grid-cols-2">
                    <div>
                        <dt className="text-muted-foreground">Sender</dt>
                        <dd>{preflight.sender}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Subject</dt>
                        <dd>{campaign.subject}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Audience and estimated volume</dt>
                        <dd>
                            {preflight.total} contacts · {preflight.eligible} eligible · {preflight.excluded} excluded
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Tracking</dt>
                        <dd>
                            Opens {preflight.tracking.open ? 'enabled' : 'disabled'} · Clicks {preflight.tracking.click ? 'enabled' : 'disabled'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Unsubscribe and sender identification</dt>
                        <dd>Visible footer + signed one-click headers · {preflight.postal_address ?? 'Postal address missing'}</dd>
                    </div>
                </dl>
                {preflight.pilot?.enabled && (
                    <p className="bg-muted/40 mt-4 rounded-lg border p-3 text-xs">
                        Restricted pilot controls are active for live sending: approved company and recipients only, up to{' '}
                        {preflight.pilot.daily_limit} submission attempts per UTC day. Simulation remains local.
                    </p>
                )}
            </Panel>
            <Metrics data={stats} />
            {campaign.delivery_mode === 'simulation' && stats.eligible_at_snapshot > 0 && (
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
                            Pre-flight checks pass for the selected mode. Live delivery still requires explicit confirmation and launch approval.
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
                                router.post(base + '/schedule', { scheduled_at: utc, confirmation: f.data.confirmation });
                            }}
                        >
                            <Field
                                label="Schedule in your local time (optional)"
                                type="datetime-local"
                                value={f.data.scheduled_at}
                                change={(v) => f.setData('scheduled_at', v)}
                            />
                            {campaign.delivery_mode === 'sendgrid_live' && (
                                <Field label="Type SEND for live delivery" value={f.data.confirmation} change={(v) => f.setData('confirmation', v)} />
                            )}
                            <Button>
                                {campaign.delivery_mode === 'simulation'
                                    ? 'Schedule simulation'
                                    : campaign.delivery_mode === 'sendgrid_sandbox'
                                      ? 'Schedule sandbox validation'
                                      : 'Confirm and schedule live sending'}
                            </Button>
                            <p className="text-muted-foreground w-full text-xs">
                                Leave blank to initiate now. Scheduling freezes the audience and email content.
                            </p>
                        </form>
                    )}
                </Panel>
            )}
            <EmailPreview
                src={base + '/preview'}
                title="Campaign preview"
                subject={String(campaign.snapshot?.subject ?? campaign.subject)}
                previewText={String(campaign.snapshot?.preview_text ?? campaign.preview_text ?? '')}
                senderName={preflight.sender_name}
                senderEmail={preflight.sender_email}
                postalAddress={preflight.postal_address}
                text={String(campaign.snapshot?.text_body ?? campaign.text_body)}
                version={campaign.html}
            />

            <Panel
                title="Recipient snapshot"
                description="Submission acceptance does not establish receiving-server delivery or inbox placement. See analytics for provider evidence."
            >
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
                <Panel title={campaign.delivery_mode === 'simulation' ? 'Simulated delivery events' : 'Submission and provider events'}>
                    {events.map((e) => (
                        <p className="border-b py-3 text-sm" key={e.id}>
                            {e.simulated ? 'Simulated' : 'SendGrid'} {e.type} · {new Date(e.created_at).toLocaleString()}
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
