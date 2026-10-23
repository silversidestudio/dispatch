import { Metrics } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Panel, Workspace } from '@/components/workspace';
import { Link } from '@inertiajs/react';
export default function Dashboard({
    organisation,
    stats,
    recentCampaigns,
    activity,
}: {
    organisation: { id: number; name: string; role: string; members: number };
    stats: Record<string, number>;
    recentCampaigns: { id: number; name: string; status: string }[];
    activity: { id: number; action: string; created_at: string }[];
}) {
    const base = `/organisations/${organisation.id}`;
    return (
        <Workspace
            title="Workspace overview"
            subtitle={`${organisation.name} · ${organisation.role.replace('_', ' ')} · ${organisation.members} members`}
            actions={
                <Button variant="outline" asChild>
                    <Link href={base}>Workspace settings</Link>
                </Button>
            }
        >
            <div className="rounded-xl border border-indigo-500/20 bg-indigo-500/5 p-5">
                <p className="text-sm font-medium">Local simulation only · External email delivery disabled</p>
                <p className="text-muted-foreground mt-1 text-xs">
                    All activity below comes from stored company data. Simulated acceptance is not confirmed inbox delivery.
                </p>
            </div>
            <Metrics data={stats} />
            <div className="grid gap-6 lg:grid-cols-2">
                <Panel title="Recent campaigns">
                    <div className="space-y-3">
                        {recentCampaigns.map((c) => (
                            <Link href={`${base}/campaigns/${c.id}`} key={c.id} className="flex justify-between rounded-xl border p-4 text-sm">
                                <span className="font-medium">{c.name}</span>
                                <span className="text-muted-foreground">{c.status}</span>
                            </Link>
                        ))}
                        {!recentCampaigns.length && (
                            <p className="text-muted-foreground py-8 text-center text-sm">
                                No campaigns yet. Prepare your audience, identity and email content to begin.
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
                            <span>{a.action.replaceAll('.', ' ').replaceAll('_', ' ')}</span>
                            <time className="text-muted-foreground text-xs">{new Date(a.created_at).toLocaleDateString()}</time>
                        </div>
                    ))}
                </Panel>
            </div>
            <Panel title="Build your next campaign">
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
