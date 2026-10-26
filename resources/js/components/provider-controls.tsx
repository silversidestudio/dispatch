import { Field } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Errors, Panel } from '@/components/workspace';
import { useForm } from '@inertiajs/react';
export type ProviderIdentity = {
    id: number;
    name: string;
    email: string;
    domain: string;
    verification_status: string;
    provider_domain_id: number | null;
    provider_authorised: boolean;
    verified_at: string | null;
    spf_status: string;
    dkim_status: string;
    dmarc_status: string;
};
export function VerifyIdentity({ companyId, identity }: { companyId: number; identity: ProviderIdentity }) {
    const f = useForm({ domain_id: String(identity.provider_domain_id ?? ''), confirmation: '' });
    return (
        <form
            className="mt-4 space-y-3"
            onSubmit={(e) => {
                e.preventDefault();
                f.post(`/platform/companies/${companyId}/identities/${identity.id}/verify`);
            }}
        >
            <Field label={`SendGrid domain ID for ${identity.email}`} value={f.data.domain_id} change={(v) => f.setData('domain_id', v)} />
            <Field label="Type AUTHORISE to review provider status" value={f.data.confirmation} change={(v) => f.setData('confirmation', v)} />
            <Errors errors={f.errors} />
            <Button variant="outline" disabled={f.processing}>
                Read stored SendGrid verification
            </Button>
            <p className="text-muted-foreground text-xs">
                Requires separately approved credentials. Reads provider state only; does not modify DNS or send email.
            </p>
        </form>
    );
}
export function TrackingReview({ companyId }: { companyId: number }) {
    const f = useForm({ open_tracking_enabled: false as boolean, click_tracking_enabled: false as boolean, evidence: '', confirmation: '' });
    return (
        <Panel
            title="Privacy and tracking review"
            description="Default: disabled. Do not enable until the business has reviewed its privacy notice, lawful basis and any applicable tracking consent requirements."
        >
            <form
                className="max-w-xl space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    f.post(`/organisations/${companyId}/tracking-review`);
                }}
            >
                <label className="flex gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={f.data.open_tracking_enabled}
                        onChange={(e) => f.setData('open_tracking_enabled', e.target.checked)}
                    />
                    Permit campaigns to opt in to open tracking
                </label>
                <label className="flex gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={f.data.click_tracking_enabled}
                        onChange={(e) => f.setData('click_tracking_enabled', e.target.checked)}
                    />
                    Permit campaigns to opt in to click tracking
                </label>
                <Field label="Documented privacy review evidence" value={f.data.evidence} change={(v) => f.setData('evidence', v)} />
                <Field label="Type REVIEWED" value={f.data.confirmation} change={(v) => f.setData('confirmation', v)} />
                <Errors errors={f.errors} />
                <Button>Record tracking decision</Button>
            </form>
        </Panel>
    );
}
