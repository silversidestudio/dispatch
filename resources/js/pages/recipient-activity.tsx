import { Company, Field, Select } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { useForm } from '@inertiajs/react';
import { RecipientActivity } from './campaign-analytics';
type Attempt = {
    id: number;
    number: number;
    outcome: string;
    http_status: number | null;
    diagnostic_code: string | null;
    provider_message_id: string;
    correlation_id: string | null;
};
export default function Recipient({
    company,
    campaign,
    recipient,
    canReconcile,
}: {
    company: Company;
    campaign: { id: number; name: string };
    recipient: RecipientActivity & {
        events: {
            id: number;
            type: string;
            provider: string;
            simulated: boolean;
            occurred_at: string | null;
            created_at: string;
            metadata: Record<string, unknown> | null;
            provider_message_id: string | null;
        }[];
        attempts: Attempt[];
    };
    canReconcile: boolean;
}) {
    const f = useForm({ decision: 'closed_without_retry', evidence: '', confirmation: '' });
    return (
        <Workspace title={recipient.email} subtitle={`${campaign.name} · Submission: ${recipient.status} · Delivery: ${recipient.delivery_status}`}>
            <Panel title="Engagement summary" description="Tracking events do not prove human activity.">
                <dl className="grid gap-4 text-sm md:grid-cols-2">
                    {[
                        ['First tracked open', recipient.first_open],
                        ['Last tracked open', recipient.last_open],
                        ['First tracked click', recipient.first_click],
                        ['Last tracked click', recipient.last_click],
                    ].map(([k, v]) => (
                        <div key={k}>
                            <dt className="text-muted-foreground">{k}</dt>
                            <dd>{v ? new Date(v).toLocaleString() : 'Not recorded'}</dd>
                        </div>
                    ))}
                </dl>
            </Panel>
            <Panel title="Chronological delivery timeline">
                <ol className="space-y-4">
                    {recipient.events.map((e) => (
                        <li key={e.id} className="rounded-xl border p-4">
                            <p className="text-muted-foreground text-xs">
                                {new Date(e.occurred_at ?? e.created_at).toLocaleString()} · {e.simulated ? 'Simulated' : e.provider}
                            </p>
                            <p className="mt-2 font-medium">{e.type}</p>
                            <p className="text-muted-foreground mt-1 text-xs break-all">{e.provider_message_id}</p>
                            {e.metadata && (
                                <dl className="mt-2 text-xs">
                                    {Object.entries(e.metadata).map(([k, v]) => (
                                        <div key={k}>
                                            {k}: {String(v ?? '—')}
                                        </div>
                                    ))}
                                </dl>
                            )}
                        </li>
                    ))}
                </ol>
            </Panel>
            <Panel title="Submission attempts" description="Uncertain attempts are held. Confirm with provider evidence before allowing any retry.">
                {recipient.attempts.map((a) => (
                    <div className="mb-4 rounded-xl border p-4 text-sm" key={a.id}>
                        <p className="font-medium">
                            Attempt {a.number} · {a.outcome}
                        </p>
                        <p className="mt-1">
                            HTTP {a.http_status ?? 'unknown'} · {a.diagnostic_code ?? '—'}
                        </p>
                        <p className="text-muted-foreground mt-2 text-xs break-all">
                            Correlation: {a.correlation_id ?? 'simulation'} · Provider ID: {a.provider_message_id}
                        </p>
                        {canReconcile && a.outcome === 'uncertain' && (
                            <form
                                className="mt-5 space-y-3"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    f.post(`/organisations/${company.id}/attempts/${a.id}/reconcile`);
                                }}
                            >
                                <Select label="Reconciliation decision" value={f.data.decision} change={(v) => f.setData('decision', v)}>
                                    <option value="closed_without_retry">Close without retry</option>
                                    <option value="confirmed_not_submitted">Provider confirms not submitted; permit retry</option>
                                </Select>
                                <Field label="Provider reconciliation evidence" value={f.data.evidence} change={(v) => f.setData('evidence', v)} />
                                <Field label="Type RECONCILE" value={f.data.confirmation} change={(v) => f.setData('confirmation', v)} />
                                <Errors errors={f.errors} />
                                <Button>Record audited decision</Button>
                            </form>
                        )}
                    </div>
                ))}
            </Panel>
        </Workspace>
    );
}
