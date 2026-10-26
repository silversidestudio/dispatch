import { Company, Field, Metrics, Page, Pages, Select } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Panel, Workspace } from '@/components/workspace';
import { Link, useForm } from '@inertiajs/react';
export type Analytics = {
    available: boolean;
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
}: {
    company: Company;
    campaign: { id: number; name: string; status: string };
    analytics: Analytics;
    recipients: Page<RecipientActivity>;
    filters: { from?: string; to?: string; search?: string; status?: string };
    recentEvents: { id: number; type: string; occurred_at: string | null; provider_message_id: string | null }[];
}) {
    const base = `/organisations/${company.id}/campaigns/${campaign.id}`;
    const f = useForm({ from: filters.from ?? '', to: filters.to ?? '', search: filters.search ?? '', status: filters.status ?? '' });
    const query = new URLSearchParams(Object.entries(f.data).filter(([, v]) => v)).toString();
    return (
        <Workspace
            title={`${campaign.name} · Analytics`}
            subtitle={`${campaign.status} · ${analytics.mode}`}
            actions={
                <Button variant="outline" asChild>
                    <a href={`${base}/analytics/export?${query}`}>Export event CSV</a>
                </Button>
            }
        >
            <div className="bg-muted/20 rounded-xl border p-5 text-sm">
                <p>
                    {analytics.available
                        ? 'SendGrid evidence: delivered means receiving-server acceptance, not inbox placement.'
                        : 'No live SendGrid evidence for this campaign. Sandbox validation and simulation are not real delivery.'}
                </p>
                <p className="text-muted-foreground mt-2">
                    Tracked opens and clicks are estimates of engagement. Privacy proxies, automatic loading, blocked images and scanners affect
                    accuracy. They do not establish a human read or click.
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
                            <option key={v}>{v}</option>
                        ))}
                    </Select>
                    <Button>Apply filters</Button>
                </form>
            </Panel>
            <Metrics data={analytics.counts} />
            <Panel
                title="Performance indicators"
                description="Delivery/bounce use unique provider-accepted recipients; open/click/unsubscribe use delivered recipients. Click-to-open uses unique tracked opening recipients. N/A means unavailable or zero denominator."
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
                <Panel title="Delivery outcome activity" description="Unique recipients with each event type; categories may overlap over time.">
                    {['processed', 'delivered', 'deferred', 'bounced', 'blocked', 'dropped', 'complaints', 'unsubscribes'].map((k) => (
                        <div className="mb-3" key={k}>
                            <div className="mb-1 flex justify-between text-sm">
                                <span className="capitalize">{k}</span>
                                <span>{analytics.counts[k]}</span>
                            </div>
                            <progress
                                className="h-2 w-full accent-indigo-600"
                                aria-label={k}
                                value={analytics.counts[k]}
                                max={Math.max(1, analytics.counts.eligible_recipients)}
                            />
                        </div>
                    ))}
                </Panel>
                <Panel
                    title="Campaign activity over time"
                    description="Daily provider delivery, tracked opens, clicks, bounce and unsubscribe activity (UTC)."
                >
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
                                            className="h-2 w-full accent-indigo-600"
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
                description="Destinations show only the origin and a stable opaque path label. Queries, fragments and actual paths are not stored. Unsubscribe and administrative paths are excluded."
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
                                    <td>{r.status}</td>
                                    <td>{r.delivery_status}</td>
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
            <Panel title="Recent verified events">
                {recentEvents.map((e) => (
                    <p className="border-b py-3 text-sm" key={e.id}>
                        {e.occurred_at ? new Date(e.occurred_at).toLocaleString() : 'Unknown event time'} · {e.type} · {e.provider_message_id}
                    </p>
                ))}
            </Panel>
        </Workspace>
    );
}
