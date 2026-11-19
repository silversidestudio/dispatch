import { BrandColorControl } from '@/components/brand-color-control';
import { Field } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Errors, Panel } from '@/components/workspace';
import { useForm } from '@inertiajs/react';
export type Branding = {
    logo_url?: string | null;
    primary_color?: string;
    secondary_color?: string | null;
    button_color?: string;
    footer_text?: string | null;
    signature_text?: string | null;
};

export function EmailBranding({ id, branding, canUpdate }: { id: number; branding?: Branding | null; canUpdate: boolean }) {
    const f = useForm({
        logo_url: branding?.logo_url ?? '',
        primary_color: branding?.primary_color ?? '#21564b',
        secondary_color: branding?.secondary_color ?? '#edf3ef',
        button_color: branding?.button_color ?? '#21564b',
        footer_text: branding?.footer_text ?? '',
        signature_text: branding?.signature_text ?? '',
    });
    return (
        <Panel
            title="Email branding"
            description="These choices are used for new Studio templates. Saved templates and scheduled campaigns keep their current design."
        >
            <form
                className="space-y-5"
                onSubmit={(e) => {
                    e.preventDefault();
                    f.patch(`/organisations/${id}/email-branding`);
                }}
            >
                <fieldset disabled={!canUpdate} className="space-y-5 disabled:opacity-70">
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Logo URL (HTTPS, optional)" value={f.data.logo_url} change={(v) => f.setData('logo_url', v)} />
                        <Field label="Default footer information" value={f.data.footer_text} change={(v) => f.setData('footer_text', v)} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <BrandColorControl
                            label="Primary brand colour"
                            value={f.data.primary_color}
                            onChange={(value) => f.setData('primary_color', value)}
                        />
                        <BrandColorControl
                            label="Secondary colour"
                            value={f.data.secondary_color}
                            onChange={(value) => f.setData('secondary_color', value)}
                        />
                        <BrandColorControl
                            label="Default button colour"
                            value={f.data.button_color}
                            onChange={(value) => f.setData('button_color', value)}
                        />
                    </div>
                    <label className="block text-sm font-medium">
                        Default email signature
                        <textarea
                            aria-label="Default email signature"
                            className="bg-background mt-2 min-h-24 w-full rounded-lg border p-3 font-normal"
                            placeholder="Company or team name, telephone and website"
                            value={f.data.signature_text}
                            onChange={(e) => f.setData('signature_text', e.target.value)}
                        />
                        <span className="text-muted-foreground mt-1 block text-xs">
                            Used for new basic emails. Existing templates and snapshots stay unchanged.
                        </span>
                    </label>
                    <Errors errors={f.errors} />
                    {canUpdate && <Button disabled={f.processing}>Save email branding</Button>}
                </fieldset>
            </form>
        </Panel>
    );
}
