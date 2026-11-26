import { ConfirmDialog } from '@/components/confirm-dialog';
import { DeliveryStatus } from '@/components/delivery-status';
import { EmailPreview } from '@/components/email-preview';
import { Company, Field, Metrics, Page, Pages, Select } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { readableLabel } from '@/lib/labels';
import { SharedData } from '@/types';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { CalendarClock, CircleCheckBig, LoaderCircle } from 'lucide-react';
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
    eventsPerPage,
    historyPerPage,
    canManage,
    canUseSimulation,
}: {
    company: Company;
    campaign: Campaign;
    preflight: {
        sender: string;
        pilot?: { enabled: boolean; recipient_allowlist_enabled: boolean; daily_limit: number };
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
    events: Page<{ id: number; type: string; created_at: string; simulated: boolean }>;
    history: Page<{ id: number; action: string; created_at: string; metadata?: { diagnostic?: string } }>;
    eventsPerPage: number;
    historyPerPage: number;
    canManage: boolean;
    canUseSimulation: boolean;
}) {
    const mode = campaign.delivery_mode;
    const deliveryLabel = mode === 'sendgrid_live' ? 'Live SendGrid' : canUseSimulation ? (mode === 'sendgrid_sandbox' ? 'SendGrid test' : 'Simulation') : 'Campaign activity';
    const { errors, name: appName } = usePage<SharedData>().props;
    const deliveryNotice =
        mode === 'sendgrid_live'
            ? 'This campaign will send to eligible recipients after you confirm the schedule.'
            : !canUseSimulation
              ? 'Review the campaign status and recorded activity below.'
              : mode === 'sendgrid_sandbox'
              ? 'SendGrid checks the request. No email is delivered to the recipient.'
              : `The campaign runs inside ${appName}. No email is sent.`;
    const base = `/organisations/${company.id}/campaigns/${campaign.id}`;
    const canManageCampaign = canManage && !campaign.archived_at && (canUseSimulation || mode === 'sendgrid_live');
    const f = useForm({ scheduled_at: '', confirmation: '' });
    const test = useForm({ email: '', mode: canUseSimulation ? 'simulation' : 'sendgrid_live', confirmation: 'PREPARE' });
    const [link, setLink] = useState('');
    const changePageSize = (panel: 'events' | 'history', value: string) => {
        router.get(
            base,
            {
                events_per_page: panel === 'events' ? value : eventsPerPage,
                history_per_page: panel === 'history' ? value : historyPerPage,
                events_page: panel === 'events' ? 1 : events.current_page,
                history_page: panel === 'history' ? 1 : history.current_page,
                page: recipients.current_page,
            },
            { preserveScroll: true, preserveState: true, only: ['events', 'history', 'eventsPerPage', 'historyPerPage'] },
        );
    };
    useEffect(() => {
        if (!['scheduled', 'processing'].includes(campaign.status)) return;
        const t = setInterval(() => router.reload({ only: ['campaign', 'stats', 'recipients', 'events', 'history'] }), 2500);
        return () => clearInterval(t);
    }, [campaign.status]);
    return (
        <Workspace
            title={campaign.name}
            subtitle={`${readableLabel(campaign.status)} · ${deliveryLabel} · ${campaign.subject}`}
            actions={
                canManageCampaign && (
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
                            <ConfirmDialog
                                trigger={<Button variant="outline">Cancel campaign</Button>}
                                title="Cancel this campaign?"
                                description="No further recipients will be submitted. A request already accepted by the provider cannot be recalled; recorded attempts will remain in campaign history."
                                confirmLabel="Cancel campaign"
                                onConfirm={() => router.post(base + '/cancel')}
                            />
                        )}
                    </div>
                )
            }
        >
            <Errors errors={errors as Record<string, string>} />
            {campaign.status === 'scheduled' && campaign.scheduled_at && (
                <div className="mb-5 flex items-start gap-3 rounded-xl border border-sky-500/30 bg-sky-500/10 p-4" role="status">
                    <CalendarClock aria-hidden="true" className="mt-0.5 size-5 shrink-0 text-sky-700 dark:text-sky-300" />
                    <div>
                        <p className="font-semibold">Campaign scheduled</p>
                        <p className="text-sm">This campaign is set to send {new Date(campaign.scheduled_at).toLocaleString()}.</p>
                    </div>
                </div>
            )}
            {campaign.status === 'processing' && (
                <div className="mb-5 flex items-start gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4" role="status">
                    <LoaderCircle aria-hidden="true" className="mt-0.5 size-5 shrink-0 animate-spin text-amber-700 dark:text-amber-300" />
                    <div>
                        <p className="font-semibold">Campaign is sending</p>
                        <p className="text-sm">Delivery activity and results will update below as the queue processes recipients.</p>
                    </div>
                </div>
            )}
            {campaign.status === 'completed' && (
                <div className="mb-5 flex items-start gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4" role="status">
                    <CircleCheckBig aria-hidden="true" className="mt-0.5 size-5 shrink-0 text-emerald-700 dark:text-emerald-300" />
                    <div>
                        <p className="font-semibold">{mode === 'simulation' && canUseSimulation ? 'Simulation completed' : 'Campaign processing completed'}</p>
                        <p className="text-sm">
                            Finished {campaign.completed_at ? new Date(campaign.completed_at).toLocaleString() : '—'}. Review delivered and failed
                            results below.
                        </p>
                    </div>
                </div>
            )}
            {canManageCampaign && (
                <Panel
                    title="Test this campaign"
                    description={canUseSimulation ? `Create a separate draft for one existing eligible contact. Simulation stays in ${appName}. SendGrid test mode checks the request but does not deliver an email.` : 'Prepare a separate live campaign for one existing eligible contact. It sends a real email only after you review it and confirm scheduling.'}
                >
                    <form
                        className="grid items-end gap-3 md:grid-cols-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            test.post(base + '/prepare-test');
                        }}
                    >
                        <Field label="Test recipient" type="email" value={test.data.email} change={(v) => test.setData('email', v)} />
                        {canUseSimulation ? (
                            <Select label="Test mode" value={test.data.mode} change={(v) => test.setData('mode', v)}>
                                <option value="simulation">Simulation</option>
                                <option value="sendgrid_sandbox">SendGrid test · no email delivered</option>
                                <option value="sendgrid_live">SendGrid live · email delivered</option>
                            </Select>
                        ) : (
                            <p className="pb-2 text-sm font-medium">Live SendGrid email</p>
                        )}
                        <Button disabled={test.processing}>{canUseSimulation ? 'Create test draft' : 'Prepare live test'}</Button>
                        <Errors errors={test.errors} />
                    </form>
                </Panel>
            )}
            <div className="text-muted-foreground flex flex-wrap items-center justify-between gap-3 text-sm">
                <p>Draft · Preview · Review · Schedule</p>
                <Button asChild size="sm" variant="outline">
                    <a href="#email-preview">Review email preview</a>
                </Button>
            </div>
            <Panel title="Final campaign review">
                <div
                    className={
                        mode === 'sendgrid_live'
                            ? 'mb-5 rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm'
                            : 'bg-muted/40 mb-5 rounded-lg border p-3 text-sm'
                    }
                >
                    <p className="font-semibold">{deliveryLabel}</p>
                    <p className="text-muted-foreground mt-1">{deliveryNotice}</p>
                </div>
                <dl className="grid gap-4 text-sm md:grid-cols-2">
                    <div>
                        <dt className="text-muted-foreground">Sender</dt>
                        <dd className="break-words">{preflight.sender}</dd>
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
                        <dd>Unsubscribe link and mail-app unsubscribe option · {preflight.postal_address ?? 'Postal address missing'}</dd>
                    </div>
                </dl>
                {mode === 'sendgrid_live' && preflight.pilot?.enabled && (
                    <p className="bg-muted/40 mt-4 rounded-lg border p-3 text-xs">
                        {preflight.pilot.recipient_allowlist_enabled
                            ? `Live sending is limited to approved test recipients, with a maximum of ${preflight.pilot.daily_limit} SendGrid submission attempts per day across the platform.`
                            : `Live sending is available to eligible, unsuppressed contacts in this workspace, with a maximum of ${preflight.pilot.daily_limit} SendGrid submission attempts per day across the platform.`}
                    </p>
                )}
            </Panel>
            {(canUseSimulation || mode !== 'simulation') && <Metrics data={stats} />}
            {canUseSimulation && campaign.delivery_mode === 'simulation' && stats.eligible_at_snapshot > 0 && (
                <Panel title="Simulation results" description={`Each contact is counted once, even if ${appName} made more than one attempt.`}>
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
                                    className="accent-primary h-2 w-full"
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
                    title="Campaign checks"
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
                            The campaign is ready for this delivery mode. A live SendGrid campaign will ask for confirmation before scheduling.
                        </p>
                    )}
                    {canManageCampaign && campaign.status === 'draft' && (
                        <Button className="mt-4" disabled={!!preflight.errors.length} onClick={() => router.post(base + '/ready')}>
                            Mark ready
                        </Button>
                    )}
                    {canManageCampaign && campaign.status === 'ready' && (
                        <form
                            className="mt-5 flex flex-wrap items-end gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                const utc = f.data.scheduled_at ? new Date(f.data.scheduled_at).toISOString() : '';
                                router.post(base + '/schedule', { scheduled_at: utc, confirmation: f.data.confirmation });
                            }}
                        >
                            <div className="bg-muted/40 w-full min-w-0 rounded-lg border p-3 text-sm">
                                <p className="font-semibold">
                                    {deliveryLabel} · {preflight.eligible} eligible {preflight.eligible === 1 ? 'recipient' : 'recipients'}
                                </p>
                                <p className="mt-1 break-words">From: {preflight.sender}</p>
                                <p className="text-muted-foreground mt-1">{deliveryNotice}</p>
                            </div>
                            <Field
                                label="Send time (optional)"
                                type="datetime-local"
                                value={f.data.scheduled_at}
                                change={(v) => f.setData('scheduled_at', v)}
                            />
                            {campaign.delivery_mode === 'sendgrid_live' && (
                                <Field label="Type SEND to confirm" value={f.data.confirmation} change={(v) => f.setData('confirmation', v)} />
                            )}
                            <Button>
                                {campaign.delivery_mode === 'simulation'
                                    ? 'Schedule simulation'
                                    : campaign.delivery_mode === 'sendgrid_sandbox'
                                      ? 'Schedule sandbox validation'
                                      : 'Confirm and schedule live sending'}
                            </Button>
                            <p className="text-muted-foreground w-full text-xs">
                                Leave the time empty to start as soon as you confirm. This campaign uses the audience and email shown in this review.
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
                title="Recipients in this campaign"
                description="SendGrid accepting a message means it reached the receiving mail server. It does not confirm inbox placement."
            >
                <div className="overflow-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr>
                                {['Recipient', 'Status', 'Attempts', ...(canUseSimulation ? ['Unsubscribe test'] : [])].map((s) => (
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
                                    <td>
                                        <DeliveryStatus
                                            status={r.status}
                                            label={mode === 'simulation' && r.status === 'accepted' ? 'Simulated acceptance' : undefined}
                                        />
                                    </td>
                                    <td>{r.attempt_count}</td>
                                    {canUseSimulation && <td>
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
                                    </td>}
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
                {(canUseSimulation || mode !== 'simulation') && <Panel title={campaign.delivery_mode === 'simulation' ? 'Simulated delivery events' : 'Submission and provider events'}>
                    <div className="mb-3 flex justify-end">
                        <Select compact label="Events per page" value={String(eventsPerPage)} change={(value) => changePageSize('events', value)}>
                            <option value="10">10 per page</option>
                            <option value="50">50 per page</option>
                            <option value="100">100 per page</option>
                        </Select>
                    </div>
                    {events.data.map((e) => (
                        <p className="border-b py-3 text-sm" key={e.id}>
                            {e.type === 'sandbox_validated'
                                ? 'Sandbox validated — no email sent'
                                : `${mode === 'sendgrid_sandbox' ? 'Sandbox' : e.simulated ? 'Simulated' : 'SendGrid'} ${e.type.replaceAll('_', ' ')}`}
                            {' · '}
                            {new Date(e.created_at).toLocaleString()}
                        </p>
                    ))}
                    {!events.data.length && <p className="text-muted-foreground text-sm">No delivery events yet.</p>}
                    <Pages page={events} />
                </Panel>}
                <Panel title="Campaign state history">
                    <div className="mb-3 flex justify-end">
                        <Select compact label="History per page" value={String(historyPerPage)} change={(value) => changePageSize('history', value)}>
                            <option value="10">10 per page</option>
                            <option value="50">50 per page</option>
                            <option value="100">100 per page</option>
                        </Select>
                    </div>
                    {history.data.map((h) => (
                        <p className="border-b py-3 text-sm" key={h.id}>
                            {h.action === 'campaign.pilot_blocked' ? 'Live send held' : readableLabel(h.action)} ·{' '}
                            {new Date(h.created_at).toLocaleString()}
                            {h.action === 'campaign.pilot_blocked' && (
                                <span className="text-muted-foreground mt-1 block">
                                    {(
                                        {
                                            pilot_configuration_invalid:
                                                'Live sending is not available yet. Ask your system administrator to check the sending settings.',
                                            pilot_recipient_not_allowed:
                                                'A recipient is not on the approved test list. Ask your system administrator to update the list.',
                                            pilot_scope_blocked:
                                                'This workspace is not enabled for live sending. Ask your system administrator for help.',
                                            pilot_daily_limit_reached:
                                                'The daily live sending limit has been reached. Try again tomorrow.',
                                            pilot_attempt_already_reserved:
                                                'This message already has a recorded attempt. Check its activity before trying again.',
                                        } as Record<string, string>
                                    )[h.metadata?.diagnostic ?? ''] ?? 'Live sending is not available. Ask your system administrator to check the sending settings.'}
                                </span>
                            )}
                        </p>
                    ))}
                    <Pages page={history} />
                </Panel>
            </div>
        </Workspace>
    );
}
