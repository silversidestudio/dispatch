import { DeliveryStatus } from '@/components/delivery-status';
import { Company, Field } from '@/components/marketing';
import { ProviderIdentity, VerifyIdentity } from '@/components/provider-controls';
import { Button } from '@/components/ui/button';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { readableLabel } from '@/lib/labels';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
export type Identity = {
    id: number;
    name: string;
    email: string;
    reply_to: string | null;
    domain: string;
    verification_status: string;
    is_default: boolean;
};
export default function Identities({
    company,
    identities,
    canManage,
    readiness,
    canVerify,
}: {
    company: Company;
    identities: (Identity & ProviderIdentity)[];
    canManage: boolean;
    readiness: { provider: string; enabled: boolean; configured: boolean; live_enabled: boolean };
    canVerify: boolean;
}) {
    const f = useForm({ name: '', email: '', reply_to: '', is_default: false as boolean });
    const credentials = useForm({ sendgrid_api_key: '', sendgrid_webhook_key: '' });
    const [id, setId] = useState<number | null>(null);
    const base = `/organisations/${company.id}/identities`;
    return (
        <Workspace
            title="Sending setup"
            subtitle="Choose the From address recipients will see and connect this company’s SendGrid account."
        >
            {canManage && (
                <Panel
                    title="SendGrid account"
                    description="This key is used only for this company. Saving a new key clears the sender check; live sending stays off until setup is complete."
                >
                    <form
                        className="space-y-4"
                        autoComplete="off"
                        onSubmit={(e) => {
                            e.preventDefault();
                            credentials.patch(`/organisations/${company.id}/sendgrid-credentials`, { onSuccess: () => credentials.reset() });
                        }}
                    >
                        <Field
                            label="SendGrid API key"
                            type="password"
                            required
                            value={credentials.data.sendgrid_api_key}
                            change={(v) => credentials.setData('sendgrid_api_key', v)}
                        />
                        <Field
                            label="Webhook verification key"
                            type="password"
                            value={credentials.data.sendgrid_webhook_key}
                            change={(v) => credentials.setData('sendgrid_webhook_key', v)}
                        />
                        <p className="text-sm">
                            SendGrid webhook address: <code>/webhooks/sendgrid/{company.id}</code>. Saving without a verification key removes the stored key.
                        </p>
                        <Errors errors={credentials.errors} />
                        <Button disabled={credentials.processing}>Save SendGrid details</Button>
                    </form>
                </Panel>
            )}
            <Panel title="Provider readiness">
                <p className="text-sm">
                    Delivery service: {readableLabel(readiness.provider)} · Account: {readiness.enabled && readiness.configured ? 'Connected' : 'Not connected'}{' '}
                    · Live sending: {readiness.live_enabled ? 'Available after campaign checks' : 'Off'}
                </p>
                <p className="text-muted-foreground mt-3 text-xs">
                    SendGrid provides the domain records to add at your DNS provider. In Cloudflare, its CNAME records must use DNS-only mode. DMARC
                    is shown separately because it needs its own review.
                </p>
            </Panel>
            <Panel title="Company identities">
                <div className="grid gap-4 md:grid-cols-2">
                    {identities.map((i) => (
                        <div key={i.id} className="rounded-xl border p-5">
                            <p className="font-medium">
                                {i.name} {i.is_default && '· Default'}
                            </p>
                            <p className="mt-1 text-sm">{i.email}</p>
                            <p className="text-muted-foreground mt-3 text-xs">
                                {i.domain} · <DeliveryStatus status={i.verification_status} /> · SPF: {readableLabel(i.spf_status)} · DKIM:{' '}
                                {readableLabel(i.dkim_status)} · DMARC: {readableLabel(i.dmarc_status)} · Last check:{' '}
                                {i.verified_at ? new Date(i.verified_at).toLocaleString() : 'Never'}
                            </p>
                            {canVerify && <VerifyIdentity companyId={company.id} identity={i} />}
                            {canManage && (
                                <Button
                                    className="mt-4"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        setId(i.id);
                                        f.setData({ name: i.name, email: i.email, reply_to: i.reply_to ?? '', is_default: i.is_default });
                                    }}
                                >
                                    Edit identity
                                </Button>
                            )}
                        </div>
                    ))}
                </div>
            </Panel>
            {canManage && (
                <Panel title={id ? 'Edit identity' : 'Add identity'}>
                    <form
                        className="max-w-xl space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            const options = {
                                onSuccess: () => {
                                    f.reset();
                                    setId(null);
                                },
                            };
                            if (id) f.patch(base + '/' + id, options);
                            else f.post(base, options);
                        }}
                    >
                        <Field label="Sender name" required value={f.data.name} change={(v) => f.setData('name', v)} />
                        <Field label="Sender email" type="email" required value={f.data.email} change={(v) => f.setData('email', v)} />
                        <Field label="Reply-to email" type="email" value={f.data.reply_to} change={(v) => f.setData('reply_to', v)} />
                        <label className="flex gap-2 text-sm">
                            <input type="checkbox" checked={f.data.is_default} onChange={(e) => f.setData('is_default', e.target.checked)} />
                            Default identity
                        </label>
                        <Errors errors={f.errors} />
                        <Button>Save sender address</Button>
                    </form>
                </Panel>
            )}
        </Workspace>
    );
}
