import { ConfirmDialog } from '@/components/confirm-dialog';
import { DeliveryStatus } from '@/components/delivery-status';
import { ProviderIdentity, VerifyIdentity } from '@/components/provider-controls';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { readableLabel } from '@/lib/labels';
import { SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
type Member = { id: number; name: string; email: string; role: string; status: string };
export default function Company({
    company,
    members,
    invites,
    audit,
    userOptions,
    counts,
    deliveryActivity,
    sendingIdentities,
}: {
    sendingIdentities: ProviderIdentity[];
    counts: Record<string, number>;
    deliveryActivity: { type: string; total: number; simulated: boolean }[];
    company: { id: number; name: string; website: string | null; postal_address: string | null };
    members: Member[];
    invites: { id: number; email: string; role: string; expires_at: string }[];
    audit: { id: number; action: string; created_at: string }[];
    userOptions: Member[];
}) {
    const base = `/platform/companies/${company.id}`;
    const f = useForm({ name: company.name, website: company.website ?? '', postal_address: company.postal_address ?? '' });
    const add = useForm({ user_id: '', role: 'viewer' });
    const invite = useForm({ email: '', role: 'viewer' });
    const transfer = useForm({ member_id: '', confirmation: '' });
    const { flash, errors } = usePage<SharedData>().props;
    return (
        <Workspace title={company.name} subtitle="Company status, membership and delivery setup.">
            <Errors errors={errors as Record<string, string>} />
            <Panel title="Company activity · aggregate only">
                <div className="flex flex-wrap gap-5 text-sm">
                    {Object.entries(counts).map(([k, v]) => (
                        <p key={k}>
                            {k}: {v}
                        </p>
                    ))}
                    {deliveryActivity.map((d) => (
                        <p key={d.type + String(d.simulated)}>
                            {d.simulated ? 'Simulated' : 'SendGrid'} {readableLabel(d.type)}: {d.total}
                        </p>
                    ))}
                </div>
            </Panel>
            <Panel
                title="Sending domain authorisation"
                description="Platform control applies to identity/domain configuration only, not private campaign content."
            >
                {sendingIdentities.map((i) => (
                    <div key={i.id} className="mb-5 rounded-lg border p-4 text-sm">
                        <p>
                            {i.name} · {i.email} · {i.domain} · <DeliveryStatus status={i.verification_status} />
                        </p>
                        <VerifyIdentity companyId={company.id} identity={i} />
                    </div>
                ))}
            </Panel>
            <Panel title="Company profile">
                <form
                    className="max-w-xl space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.patch(base);
                    }}
                >
                    {(['name', 'website', 'postal_address'] as const).map((k) => (
                        <div key={k}>
                            <Label htmlFor={k}>{{ name: 'Company name', website: 'Website', postal_address: 'Sender postal address' }[k]}</Label>
                            <Input id={k} value={f.data[k]} onChange={(e) => f.setData(k, e.target.value)} />
                        </div>
                    ))}
                    <Errors errors={f.errors} />
                    <Button disabled={f.processing}>Save company</Button>
                </form>
            </Panel>
            <Panel title="Members">
                <div className="overflow-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr>
                                <th className="p-3">Member</th>
                                <th>Role</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {members.map((m) => (
                                <tr key={m.id} className="border-t">
                                    <td className="p-3">
                                        {m.name}
                                        <div className="text-muted-foreground text-xs">
                                            {m.email} · <DeliveryStatus status={m.status} />
                                        </div>
                                    </td>
                                    <td>
                                        {m.role === 'owner' ? (
                                            'Owner'
                                        ) : (
                                            <select
                                                aria-label={`Role for ${m.name}`}
                                                className="bg-background rounded border p-2"
                                                value={m.role}
                                                onChange={(e) => router.patch(`${base}/members/${m.id}`, { role: e.target.value })}
                                            >
                                                {['administrator', 'campaign_manager', 'viewer'].map((r) => (
                                                    <option key={r} value={r}>
                                                        {readableLabel(r)}
                                                    </option>
                                                ))}
                                            </select>
                                        )}
                                    </td>
                                    <td>
                                        <ConfirmDialog
                                            trigger={
                                                <Button size="sm" variant="outline">
                                                    Remove
                                                </Button>
                                            }
                                            title={`Remove ${m.name} from this company?`}
                                            description="They will lose access to this company. Their account and activity history will remain."
                                            confirmLabel="Remove member"
                                            onConfirm={() => router.delete(`${base}/members/${m.id}`)}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Panel>
            <div className="grid gap-6 lg:grid-cols-2">
                <Panel title="Add a member">
                    <form
                        className="space-y-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            add.post(base + '/members');
                        }}
                    >
                        <Label htmlFor="existing-user">User</Label>
                        <select
                            id="existing-user"
                            required
                            className="bg-background w-full rounded border p-2"
                            value={add.data.user_id}
                            onChange={(e) => add.setData('user_id', e.target.value)}
                        >
                            <option value="">Choose an account</option>
                            {userOptions
                                .filter((u) => !members.some((m) => m.id === u.id))
                                .map((u) => (
                                    <option value={u.id} key={u.id}>
                                        {u.name} · {u.email}
                                    </option>
                                ))}
                        </select>
                        <Role value={add.data.role} change={(r) => add.setData('role', r)} />
                        <Errors errors={add.errors} />
                        <Button>Add member</Button>
                    </form>
                </Panel>
                <Panel title="Invite a user">
                    <form
                        className="space-y-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            invite.post(base + '/invitations');
                        }}
                    >
                        <Label htmlFor="email">Invitation email</Label>
                        <Input id="email" type="email" required value={invite.data.email} onChange={(e) => invite.setData('email', e.target.value)} />
                        <Role value={invite.data.role} change={(r) => invite.setData('role', r)} />
                        <Errors errors={invite.errors} />
                        <Button>Create invitation</Button>
                    </form>
                    {flash.invitation_url && (
                        <Input
                            aria-label="Invitation link"
                            readOnly
                            value={flash.invitation_url}
                            className="mt-4"
                            onFocus={(e) => e.target.select()}
                        />
                    )}
                    <ul className="mt-4 space-y-2 text-sm">
                        {invites.map((i) => (
                            <li key={i.id}>
                                {i.email} · {readableLabel(i.role)} · expires {new Date(i.expires_at).toLocaleDateString()}
                            </li>
                        ))}
                    </ul>
                </Panel>
            </div>
            <Panel
                title="Transfer ownership"
                description="Promotes an existing active member and changes previous owners to administrators. This action is audited."
            >
                <form
                    className="flex flex-wrap gap-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        transfer.post(base + '/transfer');
                    }}
                >
                    <select
                        aria-label="New owner"
                        required
                        className="bg-background rounded border p-2"
                        value={transfer.data.member_id}
                        onChange={(e) => transfer.setData('member_id', e.target.value)}
                    >
                        <option value="">Select new owner</option>
                        {members
                            .filter((m) => m.status === 'active' && m.role !== 'owner')
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
                        required
                        value={transfer.data.confirmation}
                        onChange={(e) => transfer.setData('confirmation', e.target.value)}
                    />
                    <Button>Transfer ownership</Button>
                </form>
                <Errors errors={transfer.errors} />
            </Panel>
            <Panel title="Company audit history">
                <ul className="space-y-2 text-sm">
                    {audit.map((a) => (
                        <li key={a.id}>
                            {readableLabel(a.action)} · {new Date(a.created_at).toLocaleString()}
                        </li>
                    ))}
                </ul>
            </Panel>
        </Workspace>
    );
}
function Role({ value, change }: { value: string; change: (r: string) => void }) {
    return (
        <select
            aria-label="Membership role"
            className="bg-background w-full rounded border p-2"
            value={value}
            onChange={(e) => change(e.target.value)}
        >
            {['viewer', 'campaign_manager', 'administrator'].map((r) => (
                <option key={r} value={r}>
                    {readableLabel(r)}
                </option>
            ))}
        </select>
    );
}
