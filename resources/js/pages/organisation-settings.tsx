import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
type Props = {
    organisation: { id: number; name: string; website: string | null; postal_address: string | null };
    canUpdate: boolean;
    canManageMembers: boolean;
    members: { id: number; name: string; email: string; role: string }[];
    invitations: { id: number; email: string; role: string; expires_at: string }[];
};
export default function OrganisationSettings({ organisation, canUpdate, canManageMembers, members, invitations }: Props) {
    const f = useForm({ name: organisation.name, website: organisation.website ?? '', postal_address: organisation.postal_address ?? '' });
    const transfer = useForm({ member_id: '', confirmation: '' });
    const invite = useForm({ email: '', role: 'viewer' });
    const { flash } = usePage<SharedData>().props;
    return (
        <Workspace title="Workspace settings" subtitle={organisation.name}>
            <Panel
                title="Organisation details"
                description={canUpdate ? 'Keep your workspace details up to date.' : 'Your role has read-only access.'}
            >
                <form
                    className="max-w-lg space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.patch(`/organisations/${organisation.id}`);
                    }}
                >
                    <Label htmlFor="name">Organisation name</Label>
                    <Input id="name" value={f.data.name} disabled={!canUpdate} onChange={(e) => f.setData('name', e.target.value)} />
                    <Label htmlFor="website">Company website</Label>
                    <Input id="website" disabled={!canUpdate} value={f.data.website} onChange={(e) => f.setData('website', e.target.value)} />
                    <Label htmlFor="postal-address">Sender postal address</Label>
                    <Input
                        id="postal-address"
                        disabled={!canUpdate}
                        value={f.data.postal_address}
                        onChange={(e) => f.setData('postal_address', e.target.value)}
                    />
                    <Errors errors={f.errors} />
                    {canUpdate && <Button disabled={f.processing}>Save changes</Button>}
                </form>
            </Panel>
            {canManageMembers && (
                <>
                    <Panel title="Members" description="Only the owner can grant or change membership roles.">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="text-muted-foreground border-b">
                                        <th className="p-3">Name</th>
                                        <th className="p-3">Email</th>
                                        <th className="p-3">Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {members.map((m) => (
                                        <tr key={m.id} className="border-b last:border-0">
                                            <td className="p-3">{m.name}</td>
                                            <td className="p-3">{m.email}</td>
                                            <td className="p-3">
                                                {m.role === 'owner' ? (
                                                    'Owner'
                                                ) : (
                                                    <select
                                                        aria-label={`Role for ${m.name}`}
                                                        className="bg-background rounded-md border p-2"
                                                        value={m.role}
                                                        onChange={(e) =>
                                                            router.patch(`/organisations/${organisation.id}/members/${m.id}`, {
                                                                role: e.target.value,
                                                            })
                                                        }
                                                    >
                                                        {['administrator', 'campaign_manager', 'viewer'].map((r) => (
                                                            <option key={r} value={r}>
                                                                {r.replace('_', ' ')}
                                                            </option>
                                                        ))}
                                                    </select>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Panel>
                    <Panel title="Invite a member" description="Invitations expire after seven days. No email is sent in local development.">
                        <form
                            className="flex max-w-2xl flex-wrap items-end gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                invite.post(`/organisations/${organisation.id}/invitations`, { onSuccess: () => invite.reset('email') });
                            }}
                        >
                            <div className="grow space-y-2">
                                <Label htmlFor="invite-email">Email address</Label>
                                <Input
                                    id="invite-email"
                                    type="email"
                                    required
                                    value={invite.data.email}
                                    onChange={(e) => invite.setData('email', e.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="invite-role">Role</Label>
                                <select
                                    id="invite-role"
                                    className="bg-background block h-9 rounded-md border px-3 text-sm"
                                    value={invite.data.role}
                                    onChange={(e) => invite.setData('role', e.target.value)}
                                >
                                    {['viewer', 'campaign_manager', 'administrator'].map((r) => (
                                        <option key={r} value={r}>
                                            {r.replace('_', ' ')}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <Button disabled={invite.processing}>Create invitation</Button>
                        </form>
                        <Errors errors={invite.errors} />
                        {flash.invitation_url && (
                            <div className="bg-muted/30 mt-4 rounded-lg border p-4">
                                <Label htmlFor="invitation-link">Local acceptance link · share securely</Label>
                                <Input
                                    id="invitation-link"
                                    className="mt-2"
                                    readOnly
                                    value={flash.invitation_url}
                                    onFocus={(e) => e.target.select()}
                                />
                            </div>
                        )}
                        <ul className="mt-5 space-y-3">
                            {invitations.map((i) => (
                                <li key={i.id} className="text-muted-foreground text-sm">
                                    {i.email} · {i.role.replace('_', ' ')} · expires {new Date(i.expires_at).toLocaleDateString()}
                                </li>
                            ))}
                        </ul>
                    </Panel>
                </>
            )}
            {canManageMembers && (
                <Panel title="Ownership and membership" description="Transfer ownership explicitly. The final owner cannot be removed.">
                    <form
                        className="flex flex-wrap gap-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            transfer.post(`/organisations/${organisation.id}/transfer`);
                        }}
                    >
                        <select
                            aria-label="New owner"
                            className="bg-background rounded border p-2"
                            value={transfer.data.member_id}
                            onChange={(e) => transfer.setData('member_id', e.target.value)}
                        >
                            <option value="">Choose new owner</option>
                            {members
                                .filter((m) => m.role !== 'owner')
                                .map((m) => (
                                    <option value={m.id} key={m.id}>
                                        {m.name}
                                    </option>
                                ))}
                        </select>
                        <Input
                            aria-label="Confirm transfer"
                            className="max-w-xs"
                            placeholder="Type TRANSFER"
                            value={transfer.data.confirmation}
                            onChange={(e) => transfer.setData('confirmation', e.target.value)}
                        />
                        <Button>Transfer ownership</Button>
                    </form>
                    <Errors errors={transfer.errors} />
                    <div className="mt-5 space-y-3">
                        {members
                            .filter((m) => m.role !== 'owner')
                            .map((m) => (
                                <div key={m.id} className="flex justify-between text-sm">
                                    <span>
                                        {m.name} · {m.role}
                                    </span>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => {
                                            if (confirm(`Remove ${m.name}?`)) router.delete(`/organisations/${organisation.id}/members/${m.id}`);
                                        }}
                                    >
                                        Remove member
                                    </Button>
                                </div>
                            ))}
                    </div>
                </Panel>
            )}
        </Workspace>
    );
}
