import { EmailEditor } from '@/components/email-editor';
import { EmailPreview } from '@/components/email-preview';
import { Company, Field } from '@/components/marketing';
import { PlainTextAlternative } from '@/components/plain-text-alternative';
import { Button } from '@/components/ui/button';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { router, useForm } from '@inertiajs/react';
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
    const base = `/organisations/${company.id}/templates/${template.id}`;
    return (
        <Workspace
            title={template.name}
            subtitle="Edit the message, then save to update its preview."
            actions={
                canManage && (
                    <Button variant="outline" onClick={() => router.post(base + '/duplicate', {}, { preserveState: false })}>
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
                        f.patch(base, { onSuccess: () => f.setDefaults() });
                    }}
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        {(['name', 'category', 'subject', 'preview_text'] as const).map((k) => (
                            <Field
                                key={k}
                                disabled={!canManage}
                                label={{ name: 'Template name', category: 'Category', subject: 'Subject', preview_text: 'Preview text' }[k]}
                                value={f.data[k]}
                                change={(v) => f.setData(k, v)}
                            />
                        ))}
                    </div>
                    <EmailEditor
                        onReady={(html) => {
                            f.setData('html', html);
                            f.setDefaults('html', html);
                        }}
                        buttonColour={company.email_branding?.button_color}
                        value={f.data.html}
                        editable={canManage}
                        onChange={(v) => f.setData('html', v)}
                    />
                    <PlainTextAlternative
                        companyId={company.id}
                        html={f.data.html}
                        value={f.data.text_body}
                        onChange={(v) => f.setData('text_body', v)}
                        editable={canManage}
                    />
                    <Errors errors={f.errors} />
                    {canManage && <Button disabled={f.processing}>Save template</Button>}
                </form>
            </Panel>
            <EmailPreview
                src={base + '/preview'}
                title="Template preview"
                subject={template.subject}
                previewText={template.preview_text}
                senderName={company.name}
                postalAddress={company.postal_address}
                text={template.text_body}
                version={template.html}
                unsaved={f.isDirty}
            />
        </Workspace>
    );
}
