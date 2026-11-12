import { Company, Contact, Field, Select } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { router, useForm } from '@inertiajs/react';
type Evidence = { id: number; basis: string; evidence: string; method: string; recorded_at: string };
export default function Detail({
    company,
    contact,
    eligible,
    canManage,
    canPrivacy,
    evidence,
    suppression,
    history,
}: {
    company: Company;
    contact: Contact;
    eligible: boolean;
    canManage: boolean;
    canPrivacy: boolean;
    evidence: Evidence[];
    suppression: { reason: string } | null;
    history: { id: number; action: string; created_at: string }[];
}) {
    const base = `/organisations/${company.id}/contacts/${contact.id}`;
    const f = useForm({
        email: contact.email,
        first_name: contact.first_name ?? '',
        last_name: contact.last_name ?? '',
        company_name: contact.company_name ?? '',
        telephone: contact.telephone ?? '',
        source: contact.source,
        status: contact.status,
        attributes: contact.attributes ?? {},
    });
    const e = useForm({ basis: 'consent', evidence: '', method: '', policy_version: '', recorded_at: new Date().toISOString().slice(0, 16) });
    const s = useForm({ reason: 'administratively_blocked', evidence: '' });
    const a = useForm({ key: '', value: '' });
    return (
        <Workspace
            title={contact.email}
            subtitle={`${company.name} · ${eligible ? 'Eligible for marketing' : 'Not eligible for marketing'}${suppression ? ' · Suppressed: ' + suppression.reason : ''}`}
        >
            <Panel title="Contact profile">
                <form
                    className="grid gap-4 md:grid-cols-2"
                    onSubmit={(ev) => {
                        ev.preventDefault();
                        f.patch(base);
                    }}
                >
                    {(['email', 'first_name', 'last_name', 'company_name', 'telephone', 'source'] as const).map((k) => (
                        <Field key={k} label={k.replaceAll('_', ' ')} value={f.data[k]} change={(v) => f.setData(k, v)} />
                    ))}
                    <Select label="Status" value={f.data.status} change={(v) => f.setData('status', v)}>
                        <option>active</option>
                        <option>archived</option>
                    </Select>
                    <Errors errors={f.errors} />
                    {canManage && (
                        <div>
                            <Button disabled={f.processing}>Save contact</Button>
                        </div>
                    )}
                </form>
                <div className="mt-5 space-y-2 text-sm">
                    {Object.entries(f.data.attributes).map(([k, v]) => (
                        <p key={k}>
                            {k}: {v}
                        </p>
                    ))}
                </div>
                {canManage && (
                    <div className="mt-4 flex flex-wrap items-end gap-3">
                        <Field label="Attribute key" value={a.data.key} change={(v) => a.setData('key', v)} />
                        <Field label="Attribute value" value={a.data.value} change={(v) => a.setData('value', v)} />
                        <Button
                            variant="outline"
                            onClick={() => {
                                if (/^[a-zA-Z][a-zA-Z0-9_]{0,39}$/.test(a.data.key)) {
                                    f.setData('attributes', { ...f.data.attributes, [a.data.key]: a.data.value });
                                    a.reset();
                                }
                            }}
                        >
                            Add attribute to form
                        </Button>
                    </div>
                )}
            </Panel>
            {canManage && (
                <div className="grid gap-6 lg:grid-cols-2">
                    <Panel
                        title="Record eligibility evidence"
                        description="Document the actual permission or assessment. Dispatch does not infer a lawful basis."
                    >
                        <form
                            className="space-y-4"
                            onSubmit={(ev) => {
                                ev.preventDefault();
                                e.post(base + '/eligibility');
                            }}
                        >
                            <Select label="Basis" value={e.data.basis} change={(v) => e.setData('basis', v)}>
                                <option value="consent">Explicit marketing consent</option>
                                <option value="documented_basis">Other documented eligibility assessment</option>
                                <option value="unknown">Unknown / withdraw eligibility</option>
                            </Select>
                            <Field label="Evidence" value={e.data.evidence} required change={(v) => e.setData('evidence', v)} />
                            <Field label="Method" value={e.data.method} required change={(v) => e.setData('method', v)} />
                            <Field
                                label="Evidence timestamp"
                                type="datetime-local"
                                value={e.data.recorded_at}
                                change={(v) => e.setData('recorded_at', v)}
                            />
                            <Field label="Notice or policy version" value={e.data.policy_version} change={(v) => e.setData('policy_version', v)} />
                            <Errors errors={e.errors} />
                            <Button disabled={e.processing || !!suppression}>Record evidence</Button>
                        </form>
                    </Panel>
                    <Panel
                        title="Suppress contact"
                        description="Suppression applies across all lists in this company and is not removed by ordinary edits."
                    >
                        <form
                            className="space-y-4"
                            onSubmit={(ev) => {
                                ev.preventDefault();
                                s.post(base + '/suppress');
                            }}
                        >
                            <Select label="Suppression reason" value={s.data.reason} change={(v) => s.setData('reason', v)}>
                                {['administratively_blocked', 'unsubscribed', 'suppressed', 'hard_bounce', 'complaint'].map((v) => (
                                    <option key={v}>{v}</option>
                                ))}
                            </Select>
                            <Field label="Suppression evidence" value={s.data.evidence} required change={(v) => s.setData('evidence', v)} />
                            <Errors errors={s.errors} />
                            <Button variant="outline">Record suppression</Button>
                        </form>
                    </Panel>
                </div>
            )}
            <Panel title="Eligibility history">
                {evidence.length ? (
                    evidence.map((e) => (
                        <div key={e.id} className="border-b py-3 text-sm">
                            <p className="font-medium">
                                {e.basis} · {e.method}
                            </p>
                            <p>{e.evidence}</p>
                            <time className="text-muted-foreground text-xs">{new Date(e.recorded_at).toLocaleString()}</time>
                        </div>
                    ))
                ) : (
                    <p className="text-muted-foreground text-sm">No eligibility evidence recorded.</p>
                )}
            </Panel>
            <Panel title="Contact audit history">
                {history.map((h) => (
                    <p key={h.id} className="py-2 text-sm">
                        {h.action} · {new Date(h.created_at).toLocaleString()}
                    </p>
                ))}
            </Panel>
            {canPrivacy && (
                <Panel
                    title="Personal data controls"
                    description="Owner/administrator only. Erasure is blocked during active delivery and retains minimal suppression to prevent re-contact."
                >
                    <div className="flex flex-wrap gap-3">
                        <Button variant="outline" asChild>
                            <a href={base + '/privacy-export'}>Export contact data</a>
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => {
                                const confirmation = prompt(
                                    'Type ERASE to redact this contact and completed recipient personalisation. Minimal suppression remains.',
                                );
                                if (confirmation === 'ERASE') router.post(base + '/erase', { confirmation });
                            }}
                        >
                            Erase personal data
                        </Button>
                    </div>
                </Panel>
            )}
        </Workspace>
    );
}
