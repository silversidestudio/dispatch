import { EmailEditor } from '@/components/email-editor';
import { Company, Field, Select } from '@/components/marketing';
import { PlainTextAlternative } from '@/components/plain-text-alternative';
import { Button } from '@/components/ui/button';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Campaign } from './campaigns';
import { Identity } from './identities';
import { Template } from './templates';
import { readableLabel } from '@/lib/labels';
import { SharedData } from '@/types';
export default function Editor({
    company,
    campaign,
    identities,
    lists,
    segments,
    templates,
    defaultEmail,
}: {
    company: Company;
    campaign: Campaign | null;
    identities: Identity[];
    lists: { id: number; name: string }[];
    segments: { id: number; name: string }[];
    templates: Template[];
    defaultEmail: { html: string; text_body: string };
}) {
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
        html: campaign?.html ?? defaultEmail.html,
        text_body: campaign?.text_body ?? defaultEmail.text_body,
    });
    const [count, setCount] = useState<{ total: number; eligible: number } | null>(null);
    const { name: appName } = usePage<SharedData>().props;
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
            subtitle="Give the campaign a name, choose its audience and compose the email. You can save a draft at any point."
        >
            <form
                className="space-y-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (campaign) f.patch(`${base}/campaigns/${campaign.id}`);
                    else f.post(base + '/campaigns');
                }}
            >
                <Panel title="Campaign details">
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Campaign name" required value={f.data.name} change={(v) => f.setData('name', v)} />
                        <Field label="Subject" required value={f.data.subject} change={(v) => f.setData('subject', v)} />
                        <Field label="Preview text" value={f.data.preview_text} change={(v) => f.setData('preview_text', v)} />
                        <Field label="Reply-to address (optional)" type="email" value={f.data.reply_to} change={(v) => f.setData('reply_to', v)} />
                        <Select label="From address" value={f.data.sending_identity_id} change={(v) => f.setData('sending_identity_id', v)}>
                            <option value="">Choose a sender</option>
                            {identities.map((i) => (
                                <option value={i.id} key={i.id}>
                                    {i.name} · {i.email} · {readableLabel(i.verification_status)}
                                </option>
                            ))}
                        </Select>
                    </div>
                </Panel>
                <Panel
                    title="Sending and tracking"
                    description="Choose a test or live send. Tracking is optional and remains off unless your organisation has approved it."
                >
                    <Select label="Delivery mode" value={f.data.delivery_mode} change={(v) => f.setData('delivery_mode', v)}>
                        <option value="simulation">Simulation · stays in {appName}</option>
                        <option value="sendgrid_sandbox">SendGrid test · no email delivered</option>
                        <option value="sendgrid_live">Send with SendGrid</option>
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
                    title="Audience"
                    description="Only eligible contacts are included. Suppressed and archived contacts stay out, even when they belong to the selected list."
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
                            <option value="">Choose a list or segment</option>
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
                <Panel title="Email content">
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
                            <option value="">Start without a template</option>
                            {templates.map((t) => (
                                <option key={t.id} value={t.id}>
                                    {t.name}
                                </option>
                            ))}
                        </Select>
                        <EmailEditor buttonColour={company.email_branding?.button_color} value={f.data.html} onChange={(v) => f.setData('html', v)} />
                        <PlainTextAlternative
                            companyId={company.id}
                            html={f.data.html}
                            value={f.data.text_body}
                            onChange={(v) => f.setData('text_body', v)}
                        />
                    </div>
                </Panel>
                <Errors errors={f.errors} />
                <Button disabled={f.processing}>Save campaign draft</Button>
            </form>
        </Workspace>
    );
}
