import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Panel, Workspace } from '@/components/workspace';
import { Link, router, useForm } from '@inertiajs/react';
type Props = {
    organisationPage: {
        data: { id: number; name: string; status: string; members_count: number }[];
        prev_page_url: string | null;
        next_page_url: string | null;
        current_page: number;
        last_page: number;
    };
    users: { id: number; name: string; email: string; is_platform_admin: boolean }[];
    stats: { organisations: number; users: number; active: number };
    logs: { id: number; action: string; organisation_id: number | null; created_at: string }[];
    filters: { search: string };
    runtime: Record<string, string>;
};
export default function Platform({ organisationPage: organisations, users, stats, logs, filters, runtime }: Props) {
    const f = useForm({ search: filters.search });
    return (
        <Workspace
            title="Platform administration"
            subtitle="Platform-wide operations · Restricted to super administrators"
            actions={
                <Button variant="outline" asChild>
                    <a href="/horizon">Queue monitor</a>
                </Button>
            }
        >
            <div className="grid gap-4 sm:grid-cols-3">
                {Object.entries(stats).map(([k, v]) => (
                    <div key={k} className="bg-card rounded-xl border p-5">
                        <p className="text-muted-foreground text-sm capitalize">{k}</p>
                        <p className="mt-3 text-3xl font-semibold">{v}</p>
                    </div>
                ))}
            </div>
            <Panel title="Organisations">
                <form
                    className="mb-5 flex max-w-lg gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.get('/platform');
                    }}
                >
                    <Input
                        aria-label="Search organisations"
                        placeholder="Search organisations…"
                        value={f.data.search}
                        onChange={(e) => f.setData('search', e.target.value)}
                    />
                    <Button variant="outline">Search</Button>
                </form>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="text-muted-foreground border-b">
                                {['Organisation', 'Members', 'Status', 'Action'].map((x) => (
                                    <th key={x} className="p-3">
                                        {x}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {organisations.data.map((o) => (
                                <tr key={o.id} className="border-b last:border-0">
                                    <td className="p-3 font-medium">{o.name}</td>
                                    <td className="p-3">{o.members_count}</td>
                                    <td className="p-3">{o.status}</td>
                                    <td className="p-3">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                router.patch(`/platform/organisations/${o.id}`, {
                                                    status: o.status === 'active' ? 'suspended' : 'active',
                                                })
                                            }
                                        >
                                            {o.status === 'active' ? 'Suspend' : 'Activate'}
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="mt-4 flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">
                        Page {organisations.current_page} of {organisations.last_page}
                    </span>
                    <div className="flex gap-3">
                        {organisations.prev_page_url && <Link href={organisations.prev_page_url}>Previous</Link>}
                        {organisations.next_page_url && <Link href={organisations.next_page_url}>Next</Link>}
                    </div>
                </div>
            </Panel>
            <div className="grid gap-6 lg:grid-cols-2">
                <Panel title="System configuration" description="Configured services; open Horizon for worker and queue health.">
                    <dl className="space-y-4 text-sm">
                        {Object.entries(runtime).map(([k, v]) => (
                            <div key={k} className="flex justify-between gap-4">
                                <dt className="text-muted-foreground capitalize">{k}</dt>
                                <dd>{v}</dd>
                            </div>
                        ))}
                    </dl>
                </Panel>
                <Panel title="System audit log">
                    <ul className="max-h-72 space-y-4 overflow-auto">
                        {logs.map((l) => (
                            <li key={l.id} className="flex justify-between gap-3 text-sm">
                                <span>{l.action.replaceAll('.', ' ').replaceAll('_', ' ')}</span>
                                <time className="text-muted-foreground text-xs">{new Date(l.created_at).toLocaleDateString()}</time>
                            </li>
                        ))}
                    </ul>
                </Panel>
            </div>
            <Panel title="Users" description="Latest 20 accounts. Administrator elevation uses the local CLI.">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b">
                                <th className="p-3">Name</th>
                                <th className="p-3">Email</th>
                                <th className="p-3">Platform role</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((u) => (
                                <tr key={u.id} className="border-b last:border-0">
                                    <td className="p-3">{u.name}</td>
                                    <td className="p-3">{u.email}</td>
                                    <td className="p-3">{u.is_platform_admin ? 'Super administrator' : 'Member'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Panel>
        </Workspace>
    );
}
