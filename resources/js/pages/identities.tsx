import { DeliveryStatus, StatusPill } from '@/components/delivery-status';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Company, Field } from '@/components/marketing';
import { ProviderIdentity, VerifyIdentity } from '@/components/provider-controls';
import { Button } from '@/components/ui/button';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { readableLabel } from '@/lib/labels';
import { router, useForm } from '@inertiajs/react';
import { Archive, CheckCircle2, CircleDashed, RotateCcw } from 'lucide-react';
import { useState } from 'react';
export type Identity = {
    id: number;
    name: string;
    email: string;
    reply_to: string | null;
    domain: string;
    verification_status: string;
    is_default: boolean;
    archived_at?: string | null;
    dmarc_reviewed_at?: string | null;
    dmarc_reviewed_by?: { name: string } | null;
};
export default function Identities({
    company,
    identities,
    archivedIdentities,
    webhookUrl,
    canManage,
    readiness,
    canVerify,
}: {
    company: Company;
    identities: (Identity & ProviderIdentity)[];
    archivedIdentities: (Identity & ProviderIdentity)[];
    webhookUrl: string;
    canManage: boolean;
    readiness: { provider: string; enabled: boolean; configured: boolean; webhook_configured: boolean; live_enabled: boolean };
    canVerify: boolean;
}) {
    const f = useForm({ name: '', email: '', reply_to: '', is_default: false as boolean });
    const credentials = useForm({ sendgrid_api_key: '', sendgrid_webhook_key: '' });
    const [id, setId] = useState<number | null>(null);
    const base = `/organisations/${company.id}/identities`;
    return (
        <Workspace title="Sending setup" subtitle="Choose the sender address recipients will see and connect this workspace’s SendGrid account.">
            {canManage && (
                <Panel
                    title="SendGrid account"
                    description="Saved keys stay hidden. The status below confirms which details are stored. Updating them requires sender verification again; live sending stays off until setup is complete."
                >
                    <div className="mb-4 flex flex-wrap gap-2" aria-label="Stored SendGrid credentials">
                        {[
                            { label: 'API key', configured: readiness.configured },
                            { label: 'Webhook public key', configured: readiness.webhook_configured },
                        ].map(({ label, configured }) => (
                            <span
                                key={label}
                                className={`inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm ${
                                    configured
                                        ? 'border-emerald-600/20 bg-emerald-600/10 text-emerald-700 dark:text-emerald-300'
                                        : 'text-muted-foreground bg-muted/40'
                                }`}
                            >
                                {configured ? (
                                    <CheckCircle2 className="size-4" aria-hidden="true" />
                                ) : (
                                    <CircleDashed className="size-4" aria-hidden="true" />
                                )}
                                {label}: {configured ? 'Saved' : 'Not saved'}
                            </span>
                        ))}
                    </div>
                    <form
                        className="space-y-4"
                        autoComplete="off"
                        onSubmit={(e) => {
                            e.preventDefault();
                            credentials.patch(`/organisations/${company.id}/sendgrid-credentials`, { onSuccess: () => credentials.reset() });
                        }}
                    >
                        <div>
                            <Field
                                label="SendGrid API key"
                                type="password"
                                required
                                value={credentials.data.sendgrid_api_key}
                                change={(v) => credentials.setData('sendgrid_api_key', v)}
                            />
                            <p className="text-muted-foreground mt-1 text-xs">
                                Use a custom key with Mail Send full access and Sender Authentication read access.
                            </p>
                        </div>
                        <Field
                            label="Event Webhook public key"
                            type="password"
                            value={credentials.data.sendgrid_webhook_key}
                            change={(v) => credentials.setData('sendgrid_webhook_key', v)}
                        />
                        <p className="text-sm">
                            SendGrid Event Webhook URL: <code className="break-all">{webhookUrl}</code>
                        </p>
                        <p className="text-muted-foreground text-xs">
                            Enable Signed Event Webhook in SendGrid, save it, then paste the public key shown there. The private key stays with
                            SendGrid.
                        </p>
                        <Errors errors={credentials.errors} />
                        <Button disabled={credentials.processing}>Save SendGrid details</Button>
                    </form>
                </Panel>
            )}
            <Panel title="Provider readiness">
                <dl className="divide-y text-sm">
                    <div className="flex flex-wrap items-center justify-between gap-2 py-2.5">
                        <dt className="text-muted-foreground">Delivery service</dt>
                        <dd className="font-medium">{readableLabel(readiness.provider)}</dd>
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-2 py-2.5">
                        <dt className="text-muted-foreground">SendGrid account</dt>
                        <dd>
                            <StatusPill
                                label={readiness.enabled && readiness.configured ? 'Connected' : 'Not connected'}
                                tone={readiness.enabled && readiness.configured ? 'green' : 'amber'}
                            />
                        </dd>
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-2 py-2.5">
                        <dt className="text-muted-foreground">Live sending</dt>
                        <dd>{readiness.live_enabled ? 'Available after campaign checks' : 'Off'}</dd>
                    </div>
                </dl>
                <p className="text-muted-foreground mt-3 text-xs">
                    SendGrid checks its domain authentication. Dispatch does not check DMARC DNS automatically; review the DMARC TXT record for your
                    sending domain separately. In Cloudflare, SendGrid CNAME records must use DNS-only mode.
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
                            <div className="text-muted-foreground mt-4 space-y-3 text-sm">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <span>Sending domain</span>
                                    <span className="text-foreground inline-flex items-center gap-2 font-medium">
                                        {i.domain} <DeliveryStatus status={i.verification_status} />
                                    </span>
                                </div>
                                <dl className="divide-y rounded-lg border px-3">
                                    <div className="flex items-center justify-between gap-3 py-2.5">
                                        <dt>SPF</dt>
                                        <dd>
                                            <DomainAuthStatus status={i.spf_status} />
                                        </dd>
                                    </div>
                                    <div className="flex items-center justify-between gap-3 py-2.5">
                                        <dt>DKIM</dt>
                                        <dd>
                                            <DomainAuthStatus status={i.dkim_status} />
                                        </dd>
                                    </div>
                                    <div className="flex items-center justify-between gap-3 py-2.5">
                                        <dt>Domain DMARC policy</dt>
                                        <dd>
                                            <StatusPill
                                                label={i.dmarc_reviewed_at ? 'Manual review recorded' : 'Needs manual review'}
                                                tone={i.dmarc_reviewed_at ? 'green' : 'amber'}
                                            />
                                        </dd>
                                    </div>
                                </dl>
                                <p className="text-xs">
                                    {i.dmarc_reviewed_at
                                        ? `Reviewed ${new Date(i.dmarc_reviewed_at).toLocaleDateString()} by ${i.dmarc_reviewed_by?.name ?? 'a workspace administrator'}.`
                                        : 'Dispatch does not check this DNS record automatically.'}
                                </p>
                                <p className="text-xs">
                                    Last SendGrid domain check: {i.verified_at ? new Date(i.verified_at).toLocaleString() : 'Never'}
                                </p>
                            </div>
                            {canVerify && <VerifyIdentity companyId={company.id} identity={i} />}
                            {canManage && <ReviewDmarc companyId={company.id} identity={i} />}
                            {canManage && (
                                <div className="mt-4 flex flex-wrap gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            setId(i.id);
                                            f.setData({ name: i.name, email: i.email, reply_to: i.reply_to ?? '', is_default: i.is_default });
                                        }}
                                    >
                                        Edit identity
                                    </Button>
                                    {identities.length > 1 ? (
                                        <ConfirmDialog
                                            trigger={
                                                <Button variant="outline" size="sm">
                                                    <Archive aria-hidden="true" /> Archive
                                                </Button>
                                            }
                                            title="Archive this sender identity?"
                                            description="It will no longer appear in new campaigns. Existing campaigns keep their saved sender details, and you can restore this identity later."
                                            confirmLabel="Archive identity"
                                            confirmVariant="default"
                                            onConfirm={() => router.post(`${base}/${i.id}/archive`)}
                                        />
                                    ) : (
                                        <span className="text-muted-foreground self-center text-xs">Add another sender before archiving this one.</span>
                                    )}
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            </Panel>
            {archivedIdentities.length > 0 && (
                <Panel title="Archived identities" description="Archived senders are hidden from new campaigns. Restore one at any time to use it again.">
                    <div className="divide-y rounded-lg border px-4">
                        {archivedIdentities.map((identity) => (
                            <div key={identity.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                                <div>
                                    <p className="font-medium">{identity.name}</p>
                                    <p className="text-muted-foreground text-sm">
                                        {identity.email} · Archived {identity.archived_at ? new Date(identity.archived_at).toLocaleDateString() : ''}
                                    </p>
                                </div>
                                {canManage && (
                                    <Button variant="outline" size="sm" onClick={() => router.post(`${base}/${identity.id}/restore`)}>
                                        <RotateCcw aria-hidden="true" /> Restore
                                    </Button>
                                )}
                            </div>
                        ))}
                    </div>
                </Panel>
            )}
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

function DomainAuthStatus({ status }: { status: string }) {
    const authenticated = status === 'provider_authenticated';
    return (
        <StatusPill
            label={authenticated ? 'Authenticated by SendGrid' : readableLabel(status)}
            tone={authenticated ? 'green' : status === 'not_checked' ? 'amber' : 'neutral'}
        />
    );
}

function ReviewDmarc({ companyId, identity }: { companyId: number; identity: Identity & ProviderIdentity }) {
    const form = useForm<{ confirmed: boolean }>({ confirmed: false });

    return (
        <form
            className="mt-4 space-y-3 border-t pt-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(`/organisations/${companyId}/identities/${identity.id}/dmarc-review`, { onSuccess: () => form.reset() });
            }}
        >
            <label className="flex items-start gap-2 text-sm">
                <input
                    className="mt-1 accent-primary"
                    type="checkbox"
                    required
                    checked={form.data.confirmed}
                    onChange={(event) => form.setData('confirmed', event.target.checked)}
                />
                <span>I have checked the DMARC TXT record for {identity.domain}.</span>
            </label>
            <Errors errors={form.errors} />
            <Button size="sm" variant="outline" disabled={form.processing || !form.data.confirmed}>
                {identity.dmarc_reviewed_at ? 'Record a new DMARC check' : 'Mark DMARC as reviewed'}
            </Button>
            <p className="text-muted-foreground text-xs">This records who checked and when. Dispatch does not verify the DNS record.</p>
        </form>
    );
}
