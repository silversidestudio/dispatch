import { BrandColorControl } from '@/components/brand-color-control';
import { Button } from '@/components/ui/button';
import { Errors, Panel } from '@/components/workspace';
import { useForm } from '@inertiajs/react';

const defaultWorkspaceColor = '#4f46e5';

export function WorkspaceBranding({ id, primaryColor, canUpdate }: { id: number; primaryColor?: string | null; canUpdate: boolean }) {
    const form = useForm({ primary_color: primaryColor ?? defaultWorkspaceColor });

    return (
        <Panel
            title="Workspace appearance"
            description="Choose the accent colour for buttons, focus rings and the workspace logo. This is separate from email branding."
        >
            <form
                className="max-w-xl space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.patch(`/organisations/${id}/workspace-branding`);
                }}
            >
                <fieldset disabled={!canUpdate || form.processing} className="space-y-4 disabled:opacity-70">
                    <div className="max-w-sm">
                        <BrandColorControl
                            label="Primary colour"
                            value={form.data.primary_color}
                            onChange={(value) => form.setData('primary_color', value)}
                            fallbackColor={defaultWorkspaceColor}
                        />
                    </div>
                    <Errors errors={form.errors} />
                    {canUpdate && <Button disabled={form.processing}>Save workspace appearance</Button>}
                </fieldset>
            </form>
        </Panel>
    );
}
