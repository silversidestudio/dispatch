import { EmailEditor } from '@/components/email-editor';
import { Company, Field } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Template } from './templates';
export default function Editor({ company, template, canManage }: { company: Company; template: Template; canManage: boolean }) {
    const f = useForm({
        name: template.name,
        category: template.category,
        subject: template.subject,
        preview_text: template.preview_text ?? '',
        html: template.html,
        text_body: template.text_body,
    });
    const [mobile, setMobile] = useState(false);
    const base = `/organisations/${company.id}/templates/${template.id}`;
    return (
        <Workspace
            title={template.name}
            subtitle="Email-safe rich text · save before previewing your latest changes"
            actions={
                canManage && (
                    <Button variant="outline" onClick={() => router.post(base + '/duplicate')}>
                        Duplicate template
                    </Button>
                )
            }
        >
            <Panel title="Template content">
                <form
                    className="space-y-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.patch(base);
                    }}
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        {(['name', 'category', 'subject', 'preview_text'] as const).map((k) => (
                            <Field key={k} label={k.replace('_', ' ')} value={f.data[k]} change={(v) => f.setData(k, v)} />
                        ))}
                    </div>
                    <EmailEditor value={f.data.html} editable={canManage} onChange={(v) => f.setData('html', v)} />
                    <label className="block text-sm font-medium">
                        Plain-text alternative
                        <textarea
                            aria-label="Plain-text alternative"
                            className="bg-background mt-2 min-h-40 w-full rounded-lg border p-3 font-mono text-sm"
                            value={f.data.text_body}
                            onChange={(e) => f.setData('text_body', e.target.value)}
                        />
                    </label>
                    <Errors errors={f.errors} />
                    {canManage && <Button disabled={f.processing}>Save template</Button>}
                </form>
            </Panel>
            <Panel
                title="Saved test rendering"
                description="Personalisation uses sample data. Remote HTTPS images may load; scripts and forms are blocked."
            >
                <Button variant="outline" onClick={() => setMobile(!mobile)}>
                    {mobile ? 'Desktop preview' : 'Mobile preview'}
                </Button>
                <iframe
                    key={template.html}
                    title="Template preview"
                    sandbox=""
                    referrerPolicy="no-referrer"
                    src={base + '/preview'}
                    className={`mx-auto mt-5 h-[650px] max-w-full border bg-white ${mobile ? 'w-[375px]' : 'w-full'}`}
                />
            </Panel>
        </Workspace>
    );
}
