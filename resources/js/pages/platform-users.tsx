import { ConfirmDialog } from '@/components/confirm-dialog';
import { DeliveryStatus } from '@/components/delivery-status';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Panel, Workspace } from '@/components/workspace';
import { readableLabel } from '@/lib/labels';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
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
    const edit = useForm({ name: '', email: '' });
    const [editing, setEditing] = useState<number | null>(null);
    return (
        <Workspace title="Platform users" subtitle="Accounts, company access and invitations.">
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
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                    </select>
                    <Button>Search</Button>
                </form>
                <div className="overflow-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr>
                                {['User', 'Companies', 'Status', 'Actions'].map((s) => (
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
                                                · {readableLabel(o.pivot.role)}
                                            </div>
                                        ))}
                                    </td>
                                    <td>
                                        <DeliveryStatus status={u.status} />
                                    </td>
                                    <td>
                                        {editing === u.id ? (
                                            <form
                                                className="min-w-64 space-y-2"
                                                onSubmit={(e) => {
                                                    e.preventDefault();
                                                    edit.patch(`/platform/users/${u.id}`, {
                                                        onSuccess: () => {
                                                            setEditing(null);
                                                            edit.reset();
                                                        },
                                                    });
                                                }}
                                            >
                                                <Input
                                                    aria-label="User name"
                                                    value={edit.data.name}
                                                    onChange={(e) => edit.setData('name', e.target.value)}
                                                />
                                                <Input
                                                    aria-label="User email"
                                                    type="email"
                                                    value={edit.data.email}
                                                    onChange={(e) => edit.setData('email', e.target.value)}
                                                />
                                                {edit.errors.name && <p className="text-destructive text-xs">{edit.errors.name}</p>}
                                                {edit.errors.email && <p className="text-destructive text-xs">{edit.errors.email}</p>}
                                                <div className="flex gap-2">
                                                    <Button size="sm" disabled={edit.processing}>
                                                        Save
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => {
                                                            setEditing(null);
                                                            edit.reset();
                                                        }}
                                                    >
                                                        Cancel
                                                    </Button>
                                                </div>
                                            </form>
                                        ) : (
                                            <div className="flex flex-wrap gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => {
                                                        edit.setData({ name: u.name, email: u.email });
                                                        setEditing(u.id);
                                                    }}
                                                >
                                                    Edit details
                                                </Button>
                                                <ConfirmDialog
                                                    trigger={
                                                        <Button variant="outline" size="sm">
                                                            {u.status === 'active' ? 'Suspend' : 'Activate'}
                                                        </Button>
                                                    }
                                                    title={`${u.status === 'active' ? 'Suspend' : 'Activate'} ${u.name}’s account?`}
                                                    description={
                                                        u.status === 'active'
                                                            ? 'They will lose access to Dispatch and their active sessions will be signed out. You can activate the account again later.'
                                                            : 'They will be able to sign in again using their existing account.'
                                                    }
                                                    confirmLabel={u.status === 'active' ? 'Suspend account' : 'Activate account'}
                                                    confirmVariant={u.status === 'active' ? 'destructive' : 'default'}
                                                    onConfirm={() =>
                                                        router.patch(`/platform/users/${u.id}/status`, {
                                                            status: u.status === 'active' ? 'suspended' : 'active',
                                                        })
                                                    }
                                                />
                                            </div>
                                        )}
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
                        {i.email} · {readableLabel(i.role)} · {new Date(i.expires_at) > new Date() ? 'Pending' : 'Expired'}
                    </p>
                ))}
            </Panel>
            <Panel title="Account audit history">
                {audit.map((a) => (
                    <p key={a.id} className="py-2 text-sm">
                        {readableLabel(a.action)} · account {a.metadata?.account_id ?? '—'} · {new Date(a.created_at).toLocaleString()}
                    </p>
                ))}
            </Panel>
        </Workspace>
    );
}
