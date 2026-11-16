import { EmailBranding, type Branding } from '@/components/email-branding';
import { TrackingReview } from '@/components/provider-controls';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { readableLabel } from '@/lib/labels';
import { router, useForm } from '@inertiajs/react';
type Props = {
    organisation: {
        id: number;
        name: string;
        website: string | null;
        postal_address: string | null;
        email_branding?: Branding | null;
        tracking: { open: boolean; click: boolean; reviewed_at: string | null };
    };
    canUpdate: boolean;
    canManageMembers: boolean;
    members: { id: number; name: string; email: string; role: string }[];
};
export default function OrganisationSettings({ organisation, canUpdate, canManageMembers, members }: Props) {
    const f = useForm({ name: organisation.name, website: organisation.website ?? '', postal_address: organisation.postal_address ?? '' });
    const transfer = useForm({ member_id: '', confirmation: '' });
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
            <EmailBranding id={organisation.id} branding={organisation.email_branding} canUpdate={canUpdate} />
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
                                                                {readableLabel(r)}
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
                                        {m.name} · {readableLabel(m.role)}
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
            {canUpdate && <TrackingReview companyId={organisation.id} tracking={organisation.tracking} />}
        </Workspace>
    );
}
