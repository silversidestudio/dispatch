import { EmailEditor } from '@/components/email-editor';
import { Company, Field, Select } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Campaign } from './campaigns';
import { Identity } from './identities';
import { Template } from './templates';
export default function Editor({
    company,
    campaign,
    identities,
    lists,
    segments,
    templates,
}: {
    company: Company;
    campaign: Campaign | null;
    identities: Identity[];
    lists: { id: number; name: string }[];
    segments: { id: number; name: string }[];
    templates: Template[];
}) {
    const footer =
        '<h1>Hello {{first_name}}</h1><p>Write something useful for your audience.</p><p>{{sender_name}} · {{sender_address}}</p><p><a href="{{unsubscribe_url}}">Unsubscribe</a></p>';
    const f = useForm({
        delivery_mode: campaign?.delivery_mode ?? 'simulation',
        open_tracking: campaign?.open_tracking ?? false,
        click_tracking: campaign?.click_tracking ?? false,
        name: campaign?.name ?? '',
        subject: campaign?.subject ?? '',
        preview_text: campaign?.preview_text ?? '',
        sending_identity_id: String(campaign?.sending_identity_id ?? identities.find((i) => i.is_default)?.id ?? identities[0]?.id ?? ''),
        reply_to: campaign?.reply_to ?? '',
        audience_type: campaign?.audience_type ?? 'list',
        audience_id: String(campaign?.audience_id ?? ''),
        html: campaign?.html ?? footer,
        text_body: campaign?.text_body ?? 'Hello {{first_name}}\n\n{{sender_name}} · {{sender_address}}\nUnsubscribe: {{unsubscribe_url}}',
    });
    const [count, setCount] = useState<{ total: number; eligible: number } | null>(null);
    const base = `/organisations/${company.id}`;
    useEffect(() => {
        setCount(null);
        if (!f.data.audience_id) return;
        const controller = new AbortController();
        fetch(`${base}/audience-preview?type=${f.data.audience_type}&id=${f.data.audience_id}`, {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
        })
            .then((r) => (r.ok ? r.json() : null))
            .then(setCount)
            .catch(() => {});
        return () => controller.abort();
    }, [base, f.data.audience_type, f.data.audience_id]);
    return (
        <Workspace
            title={campaign ? 'Edit campaign draft' : 'Create campaign'}
            subtitle="Choose an audience, design your email and save a draft before running pre-flight checks."
        >
            <form
                className="space-y-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (campaign) f.patch(`${base}/campaigns/${campaign.id}`);
                    else f.post(base + '/campaigns');
                }}
            >
                <Panel title="1 · Campaign details">
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Campaign name" required value={f.data.name} change={(v) => f.setData('name', v)} />
                        <Field label="Subject" required value={f.data.subject} change={(v) => f.setData('subject', v)} />
                        <Field label="Preview text" value={f.data.preview_text} change={(v) => f.setData('preview_text', v)} />
                        <Field label="Reply-to override" type="email" value={f.data.reply_to} change={(v) => f.setData('reply_to', v)} />
                        <Select label="Sending identity" value={f.data.sending_identity_id} change={(v) => f.setData('sending_identity_id', v)}>
                            <option value="">Choose identity</option>
                            {identities.map((i) => (
                                <option value={i.id} key={i.id}>
                                    {i.name} · {i.email} · unverified, simulation only
                                </option>
                            ))}
                        </Select>
                    </div>
                </Panel>
                <Panel
                    title="Provider and privacy controls"
                    description="Live sending requires verified identity, explicit confirmation and separately approved configuration. Tracking requires a documented company review."
                >
                    <Select label="Delivery mode" value={f.data.delivery_mode} change={(v) => f.setData('delivery_mode', v)}>
                        <option value="simulation">Local simulation</option>
                        <option value="sendgrid_sandbox">SendGrid sandbox validation</option>
                        <option value="sendgrid_live">SendGrid live (approval required)</option>
                    </Select>
                    <div className="mt-4 flex flex-wrap gap-4">
                        <label className="flex gap-2 text-sm">
                            <input type="checkbox" checked={f.data.open_tracking} onChange={(e) => f.setData('open_tracking', e.target.checked)} />
                            Enable tracked opens
                        </label>
                        <label className="flex gap-2 text-sm">
                            <input type="checkbox" checked={f.data.click_tracking} onChange={(e) => f.setData('click_tracking', e.target.checked)} />
                            Enable tracked clicks
                        </label>
                    </div>
                </Panel>
                <Panel
                    title="2 · Audience"
                    description="Unknown, archived or suppressed contacts are excluded centrally. Eligibility is checked again before each simulated submission."
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <Select
                            label="Audience type"
                            value={f.data.audience_type}
                            change={(v) => {
                                f.setData('audience_type', v);
                                f.setData('audience_id', '');
                            }}
                        >
                            <option value="list">Mailing list</option>
                            <option value="segment">Dynamic segment</option>
                        </Select>
                        <Select label="Audience" value={f.data.audience_id} change={(v) => f.setData('audience_id', v)}>
                            <option value="">Choose audience</option>
                            {(f.data.audience_type === 'list' ? lists : segments).map((a) => (
                                <option value={a.id} key={a.id}>
                                    {a.name}
                                </option>
                            ))}
                        </Select>
                    </div>
                    {count && (
                        <p role="status" className="mt-4 text-sm">
                            {count.total} contacts · {count.eligible} eligible · {count.total - count.eligible} excluded
                        </p>
                    )}
                </Panel>
                <Panel title="3 · Email content">
                    <div className="space-y-5">
                        <Select
                            label="Start from a template"
                            value=""
                            change={(v) => {
                                const t = templates.find((t) => t.id === Number(v));
                                if (t)
                                    f.setData({
                                        ...f.data,
                                        html: t.html,
                                        text_body: t.text_body,
                                        subject: t.subject,
                                        preview_text: t.preview_text ?? '',
                                    });
                            }}
                        >
                            <option value="">Choose a saved template</option>
                            {templates.map((t) => (
                                <option key={t.id} value={t.id}>
                                    {t.name}
                                </option>
                            ))}
                        </Select>
                        <EmailEditor value={f.data.html} onChange={(v) => f.setData('html', v)} />
                        <label className="block text-sm font-medium">
                            Plain-text alternative
                            <textarea
                                aria-label="Plain-text alternative"
                                className="bg-background mt-2 min-h-40 w-full rounded-lg border p-3 font-mono text-sm"
                                value={f.data.text_body}
                                onChange={(e) => f.setData('text_body', e.target.value)}
                            />
                        </label>
                    </div>
                </Panel>
                <Errors errors={f.errors} />
                <Button disabled={f.processing}>Save campaign draft</Button>
            </form>
        </Workspace>
    );
}
