import { DeliveryStatus } from '@/components/delivery-status';
import { Company, Field, Metrics, Page, Pages, Select } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Panel, Workspace } from '@/components/workspace';
import { modeLabel, readableLabel } from '@/lib/labels';
import { Link, router, useForm } from '@inertiajs/react';
import { Activity, Download, Eye, MousePointerClick, Send, ShieldCheck } from 'lucide-react';
export type Analytics = {
    available: boolean;
    tracking: { open: boolean; click: boolean };
    mode: string;
    counts: Record<string, number>;
    rates: Record<string, number | null>;
    links: { url: string; total: number; unique: number; delivered_percentage: number | null }[];
    activity: { day: string; delivered: number; opens: number; clicks: number; bounces: number; unsubscribes: number }[];
};
export type RecipientActivity = {
    id: number;
    email: string;
    status: string;
    delivery_status: string;
    last_event: string | null;
    last_event_at: string | null;
    first_open: string | null;
    last_open: string | null;
    open_events: number;
    first_click: string | null;
    last_click: string | null;
    click_events: number;
    unsubscribed: boolean;
};
export default function CampaignAnalytics({
    company,
    campaign,
    analytics,
    recipients,
    filters,
    recentEvents,
    recentEventsPerPage,
}: {
    company: Company;
    campaign: { id: number; name: string; status: string };
    analytics: Analytics;
    recipients: Page<RecipientActivity>;
    filters: { from?: string; to?: string; search?: string; status?: string };
    recentEvents: Page<{ id: number; type: string; occurred_at: string | null; provider_message_id: string | null }>;
    recentEventsPerPage: number;
}) {
    const base = `/organisations/${company.id}/campaigns/${campaign.id}`;
    const f = useForm({ from: filters.from ?? '', to: filters.to ?? '', search: filters.search ?? '', status: filters.status ?? '' });
    const filterParams = Object.fromEntries(Object.entries(filters).filter((entry): entry is [string, string] => Boolean(entry[1])));
    const query = new URLSearchParams(filterParams).toString();
    const changeRecentEventsPageSize = (value: string) =>
        router.get(
            `${base}/analytics`,
            { ...filterParams, recent_events_per_page: value, recent_events_page: 1, page: recipients.current_page },
            { preserveScroll: true, preserveState: true, only: ['recentEvents', 'recentEventsPerPage'] },
        );
    return (
        <Workspace
            title={`${campaign.name} · Analytics`}
            subtitle={`${readableLabel(campaign.status)} · ${modeLabel(analytics.mode)}`}
            actions={
                <Button variant="outline" asChild>
                    <a href={`${base}/analytics/export?${query}`}>
                        <Download aria-hidden="true" className="size-4" />
                        Export event CSV
                    </a>
                </Button>
            }
        >
            <div className="bg-muted/20 rounded-xl border p-5 text-sm">
                <p>
                    {analytics.available
                        ? 'Delivered means the receiving mail server accepted the message. It does not confirm that the message reached the inbox.'
                        : 'This campaign has no live delivery results. A SendGrid test and a simulation do not deliver messages.'}
                </p>
                <p className="text-muted-foreground mt-2">
                    Open and click counts are estimates. Privacy services and automated security checks can add or hide activity, so they do not
                    confirm that a person read or clicked the email.
                </p>
            </div>
            <Panel title="Reporting filters">
                <form
                    className="grid items-end gap-3 md:grid-cols-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.get(base + '/analytics');
                    }}
                >
                    <Field label="From date (UTC)" type="date" value={f.data.from} change={(v) => f.setData('from', v)} />
                    <Field label="To date (UTC)" type="date" value={f.data.to} change={(v) => f.setData('to', v)} />
                    <Field label="Search recipients" value={f.data.search} change={(v) => f.setData('search', v)} />
                    <Select label="Event filter" value={f.data.status} change={(v) => f.setData('status', v)}>
                        <option value="">All activity</option>
                        {['delivered', 'deferred', 'bounce', 'blocked', 'dropped', 'open', 'click', 'unsubscribe', 'spamreport'].map((v) => (
                            <option key={v} value={v}>
                                {v === 'spamreport' ? 'Spam complaint' : readableLabel(v)}
                            </option>
                        ))}
                    </Select>
                    <Button>Apply filters</Button>
                </form>
            </Panel>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {[
                    { label: 'Provider accepted', key: 'accepted', icon: Send, enabled: analytics.available },
                    { label: 'Delivered', key: 'delivered', icon: ShieldCheck, enabled: analytics.available },
                    { label: 'Unique tracked opens', key: 'unique_opens', icon: Eye, enabled: analytics.tracking.open },
                    { label: 'Unique tracked clicks', key: 'unique_clicks', icon: MousePointerClick, enabled: analytics.tracking.click },
                ].map(({ label, key, icon: Icon, enabled }) => (
                    <div key={key} className="bg-card rounded-xl border p-4">
                        <p className="text-muted-foreground flex items-center gap-2 text-sm">
                            <Icon aria-hidden="true" className="size-4" />
                            {label}
                        </p>
                        <p className="mt-2 text-2xl font-semibold">{enabled ? analytics.counts[key] : '—'}</p>
                        {!enabled && (
                            <p className="text-muted-foreground mt-1 text-xs">
                                {analytics.available ? 'Tracking off for this campaign' : 'No live delivery'}
                            </p>
                        )}
                    </div>
                ))}
            </div>
            <details className="bg-card rounded-xl border p-4">
                <summary className="cursor-pointer text-sm font-medium">All campaign counts</summary>
                <div className="mt-4">
                    <Metrics data={analytics.counts} />
                </div>
            </details>
            <Panel
                title="Performance indicators"
                description="Rates count unique recipients. Open, click and unsubscribe rates use delivered recipients; click-to-open uses recipients with a tracked open. N/A means there is no usable count yet."
            >
                <div className="grid gap-4 sm:grid-cols-3">
                    {Object.entries(analytics.rates).map(([k, v]) => (
                        <div key={k} className="rounded-lg border p-4">
                            <p className="text-muted-foreground text-sm capitalize">{k.replaceAll('_', ' ')} rate</p>
                            <p className="mt-3 text-2xl font-semibold">{v === null ? 'N/A' : `${v}%`}</p>
                        </div>
                    ))}
                </div>
            </Panel>
            <div className="grid gap-6 lg:grid-cols-2">
                <Panel
                    title="Delivery activity"
                    description="Unique recipients for each event. One recipient may appear in more than one category over time."
                >
                    {['processed', 'delivered', 'deferred', 'bounced', 'blocked', 'dropped', 'complaints', 'unsubscribes'].map((k) => (
                        <div className="mb-3" key={k}>
                            <div className="mb-1 flex justify-between text-sm">
                                <span className="capitalize">{k}</span>
                                <span>{analytics.counts[k]}</span>
                            </div>
                            <progress
                                className="accent-primary h-2 w-full"
                                aria-label={k}
                                value={analytics.counts[k]}
                                max={Math.max(1, analytics.counts.eligible_recipients)}
                            />
                        </div>
                    ))}
                </Panel>
                <Panel title="Campaign activity over time" description="Daily delivery and tracked engagement events. Dates use UTC.">
                    <div className="space-y-4">
                        {analytics.activity.map((a) => (
                            <div key={a.day} className="rounded-lg border p-3">
                                <p className="text-muted-foreground mb-3 text-xs">{a.day}</p>
                                {[
                                    ['Delivered', a.delivered],
                                    ['Tracked opens', a.opens],
                                    ['Tracked clicks', a.clicks],
                                    ['Bounces', a.bounces],
                                    ['Unsubscribes', a.unsubscribes],
                                ].map(([name, n]) => (
                                    <div key={name} className="flex items-center gap-3 text-xs">
                                        <span className="w-24 shrink-0">{name}</span>
                                        <progress
                                            className="accent-primary h-2 w-full"
                                            aria-label={`${name} ${a.day}`}
                                            value={Number(n)}
                                            max={Math.max(
                                                1,
                                                ...analytics.activity.map((d) => Math.max(d.delivered, d.opens, d.clicks, d.bounces, d.unsubscribes)),
                                            )}
                                        />
                                        <span>{n}</span>
                                    </div>
                                ))}
                            </div>
                        ))}
                        {!analytics.activity.length && (
                            <p className="text-muted-foreground py-10 text-center text-sm">No provider events in this range.</p>
                        )}
                    </div>
                </Panel>
            </div>
            <Panel
                title="Link performance"
                description="For privacy, this report hides full link addresses and removes query details. Unsubscribe and administrative links are not counted."
            >
                <div className="overflow-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr>
                                {['Safe destination label', 'Click events', 'Unique recipients', '% delivered'].map((k) => (
                                    <th className="p-3" key={k}>
                                        {k}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {analytics.links.map((l) => (
                                <tr key={l.url} className="border-t">
                                    <td className="p-3">{l.url}</td>
                                    <td>{l.total}</td>
                                    <td>{l.unique}</td>
                                    <td>{l.delivered_percentage === null ? 'N/A' : l.delivered_percentage + '%'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Panel>
            <Panel title="Recipient activity">
                <div className="overflow-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr>
                                {['Recipient', 'Submission', 'Delivery', 'Last event', 'Tracked opens', 'Tracked clicks', 'Unsubscribed'].map((s) => (
                                    <th key={s} className="p-3">
                                        {s}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {recipients.data.map((r) => (
                                <tr key={r.id} className="border-t">
                                    <td className="p-3">
                                        <Link className="underline" href={`${base}/activity/${r.id}`}>
                                            {r.email}
                                        </Link>
                                    </td>
                                    <td>
                                        <DeliveryStatus
                                            status={r.status}
                                            label={!analytics.available && r.status === 'accepted' ? 'Simulated acceptance' : undefined}
                                        />
                                    </td>
                                    <td>
                                        <DeliveryStatus status={r.delivery_status} />
                                    </td>
                                    <td>
                                        {r.last_event ?? '—'}
                                        <div className="text-muted-foreground text-xs">
                                            {r.last_event_at ? new Date(r.last_event_at).toLocaleString() : ''}
                                        </div>
                                    </td>
                                    <td>{r.open_events}</td>
                                    <td>{r.click_events}</td>
                                    <td>{r.unsubscribed ? 'Yes' : 'No'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pages page={recipients} />
            </Panel>
            <Panel title="Recent SendGrid events" description="Events received in the selected date range.">
                <div className="mb-3 flex justify-end">
                    <Select compact label="Events per page" value={String(recentEventsPerPage)} change={changeRecentEventsPageSize}>
                        <option value="10">10 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </Select>
                </div>
                {!recentEvents.data.length && (
                    <p className="text-muted-foreground flex items-center gap-2 text-sm">
                        <Activity aria-hidden="true" className="size-4" />
                        No SendGrid events in this date range.
                    </p>
                )}
                {recentEvents.data.map((e) => (
                    <p className="border-b py-3 text-sm" key={e.id}>
                        {e.occurred_at ? new Date(e.occurred_at).toLocaleString() : 'Unknown event time'} · {readableLabel(e.type)} ·{' '}
                        {e.provider_message_id}
                    </p>
                ))}
                <Pages page={recentEvents} />
            </Panel>
        </Workspace>
    );
}
