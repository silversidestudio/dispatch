import { DeliveryStatus } from '@/components/delivery-status';
import { Metrics } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Panel, Workspace } from '@/components/workspace';
import { readableLabel } from '@/lib/labels';
import { Link } from '@inertiajs/react';
import { Analytics } from './campaign-analytics';
export default function Dashboard({
    organisation,
    stats,
    recentCampaigns,
    activity,
    performance,
    alerts,
    providerStatus,
}: {
    organisation: { id: number; name: string; role: string; members: number };
    providerStatus: { provider: string; configured: boolean; live_enabled: boolean };
    performance: { id: number; name: string; analytics: Analytics }[];
    alerts: { id: number; campaign_id: number; type: string; occurred_at: string | null }[];
    stats: Record<string, number>;
    recentCampaigns: { id: number; name: string; status: string }[];
    activity: { id: number; action: string; created_at: string }[];
}) {
    const base = `/organisations/${organisation.id}`;
    return (
        <Workspace
            title="Workspace overview"
            subtitle={`${organisation.name} · ${readableLabel(organisation.role)} · ${organisation.members} members`}
            actions={
                <Button variant="outline" asChild>
                    <Link href={base}>Workspace settings</Link>
                </Button>
            }
        >
            <div className="border-primary/20 bg-primary/5 rounded-xl border p-5">
                <p className="text-sm font-medium">
                    Delivery provider: {providerStatus.provider} · Live delivery{' '}
                    {providerStatus.live_enabled ? 'enabled by configuration; approval still required' : 'disabled'}
                </p>
                <p className="text-muted-foreground mt-1 text-xs">
                    All activity below comes from stored company data. Simulated acceptance is not confirmed inbox delivery.
                </p>
            </div>
            <Metrics data={stats} />
            <Panel
                title="Recent campaign performance"
                description="SendGrid results are shown separately from simulations. Rates appear when there are enough delivery events to calculate them."
            >
                <div className="grid gap-4 md:grid-cols-2">
                    {performance.map((c) => (
                        <Link key={c.id} href={`${base}/campaigns/${c.id}/analytics`} className="rounded-xl border p-4 text-sm">
                            <p className="font-medium">{c.name}</p>
                            <p className="text-muted-foreground mt-2">
                                Delivered {c.analytics.counts.delivered} · Bounce rate{' '}
                                {c.analytics.rates.bounce === null ? 'N/A' : c.analytics.rates.bounce + '%'} · Delivery rate{' '}
                                {c.analytics.rates.delivery === null ? 'N/A' : c.analytics.rates.delivery + '%'}
                            </p>
                            <p className="mt-2 text-xs">
                                Tracked open rate {c.analytics.rates.unique_open === null ? 'N/A' : c.analytics.rates.unique_open + '%'} · Tracked
                                click rate {c.analytics.rates.unique_click === null ? 'N/A' : c.analytics.rates.unique_click + '%'}
                            </p>
                            <progress
                                aria-label={`Delivery progress ${c.name}`}
                                className="accent-primary mt-3 h-2 w-full"
                                value={c.analytics.counts.delivered}
                                max={Math.max(1, c.analytics.counts.eligible_recipients)}
                            />
                        </Link>
                    ))}
                </div>
            </Panel>
            {alerts.length > 0 && (
                <Panel title="Recent provider alerts">
                    {alerts.map((a) => (
                        <p key={a.id} className="py-2 text-sm">
                            {a.type} · Campaign {a.campaign_id} · {a.occurred_at ? new Date(a.occurred_at).toLocaleString() : ''}
                        </p>
                    ))}
                </Panel>
            )}
            <div className="grid gap-6 lg:grid-cols-2">
                <Panel title="Recent campaigns">
                    <div className="space-y-3">
                        {recentCampaigns.map((c) => (
                            <Link href={`${base}/campaigns/${c.id}`} key={c.id} className="flex justify-between rounded-xl border p-4 text-sm">
                                <span className="font-medium">{c.name}</span>
                                <DeliveryStatus status={c.status} />
                            </Link>
                        ))}
                        {!recentCampaigns.length && (
                            <p className="text-muted-foreground py-8 text-center text-sm">
                                No campaigns yet.
                            </p>
                        )}
                        <Button variant="outline" asChild>
                            <Link href={base + '/campaigns'}>View campaigns</Link>
                        </Button>
                    </div>
                </Panel>
                <Panel title="Recent workspace activity">
                    {activity.map((a) => (
                        <div className="flex justify-between gap-3 border-b py-3 text-sm" key={a.id}>
                            <span>{readableLabel(a.action)}</span>
                            <time className="text-muted-foreground text-xs">{new Date(a.created_at).toLocaleDateString()}</time>
                        </div>
                    ))}
                </Panel>
            </div>
            <Panel title="Workspace shortcuts">
                <div className="flex flex-wrap gap-3">
                    {[
                        ['Contacts', 'contacts'],
                        ['Mailing lists & segments', 'audiences'],
                        ['Email templates', 'templates'],
                        ['Sending identities', 'identities'],
                        ['Simulation reports', 'reports'],
                    ].map(([name, path]) => (
                        <Button key={path} variant="outline" asChild>
                            <Link href={`${base}/${path}`}>{name}</Link>
                        </Button>
                    ))}
                </div>
            </Panel>
        </Workspace>
    );
}
