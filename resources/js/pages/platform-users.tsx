import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Panel, Workspace } from '@/components/workspace';
import { Link, router, useForm } from '@inertiajs/react';
type Account = {
    id: number;
    name: string;
    email: string;
    status: string;
    is_platform_admin: boolean;
    organisations: { id: number; name: string; pivot: { role: string } }[];
};
export default function Users({
    accounts,
    filters,
    invitations,
    audit,
}: {
    accounts: { data: Account[]; prev_page_url: string | null; next_page_url: string | null };
    filters: { search?: string; status?: string };
    invitations: { id: number; email: string; role: string; expires_at: string }[];
    audit: { id: number; action: string; metadata: { account_id?: number }; created_at: string }[];
}) {
    const f = useForm({ search: filters.search ?? '', status: filters.status ?? '' });
    return (
        <Workspace title="Platform users" subtitle="Account controls and memberships">
            <Panel title="User directory">
                <form
                    className="mb-5 flex gap-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.get('/platform/users');
                    }}
                >
                    <Input
                        aria-label="Search users"
                        placeholder="Search name or email"
                        value={f.data.search}
                        onChange={(e) => f.setData('search', e.target.value)}
                    />
                    <select
                        aria-label="Account status"
                        className="bg-background rounded border"
                        value={f.data.status}
                        onChange={(e) => f.setData('status', e.target.value)}
                    >
                        <option value="">All statuses</option>
                        <option>active</option>
                        <option>suspended</option>
                    </select>
                    <Button>Search</Button>
                </form>
                <div className="overflow-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr>
                                {['User', 'Companies', 'Status', 'Action'].map((s) => (
                                    <th key={s} className="p-3">
                                        {s}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {accounts.data.map((u) => (
                                <tr key={u.id} className="border-t">
                                    <td className="p-3">
                                        {u.name} {u.is_platform_admin && '· Platform admin'}
                                        <div className="text-muted-foreground">{u.email}</div>
                                    </td>
                                    <td>
                                        {u.organisations.map((o) => (
                                            <div key={o.id}>
                                                <Link href={`/platform/companies/${o.id}`} className="underline">
                                                    {o.name}
                                                </Link>{' '}
                                                · {o.pivot.role}
                                            </div>
                                        ))}
                                    </td>
                                    <td>{u.status}</td>
                                    <td>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                if (confirm(`Change account status for ${u.email}?`))
                                                    router.patch(`/platform/users/${u.id}/status`, {
                                                        status: u.status === 'active' ? 'suspended' : 'active',
                                                    });
                                            }}
                                        >
                                            {u.status === 'active' ? 'Suspend' : 'Activate'}
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="mt-4 flex gap-4">
                    {accounts.prev_page_url && <Link href={accounts.prev_page_url}>Previous</Link>}
                    {accounts.next_page_url && <Link href={accounts.next_page_url}>Next</Link>}
                </div>
            </Panel>
            <Panel title="Recent invitations">
                {invitations.map((i) => (
                    <p key={i.id} className="py-2 text-sm">
                        {i.email} · {i.role} · {new Date(i.expires_at) > new Date() ? 'Pending' : 'Expired'}
                    </p>
                ))}
            </Panel>
            <Panel title="Account audit history">
                {audit.map((a) => (
                    <p key={a.id} className="py-2 text-sm">
                        {a.action} · account {a.metadata?.account_id ?? '—'} · {new Date(a.created_at).toLocaleString()}
                    </p>
                ))}
            </Panel>
        </Workspace>
    );
}
