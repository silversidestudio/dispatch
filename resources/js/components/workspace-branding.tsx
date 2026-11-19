import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
                    <div className="flex flex-wrap items-end gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="workspace-primary-color">Primary colour</Label>
                            <Input
                                id="workspace-primary-color"
                                type="color"
                                className="h-11 w-20 cursor-pointer p-1"
                                value={/^#[0-9a-fA-F]{6}$/.test(form.data.primary_color) ? form.data.primary_color : defaultWorkspaceColor}
                                onChange={(event) => form.setData('primary_color', event.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="workspace-primary-hex">Hex value</Label>
                            <Input
                                id="workspace-primary-hex"
                                className="w-32 font-mono uppercase"
                                value={form.data.primary_color}
                                onChange={(event) => form.setData('primary_color', event.target.value)}
                                aria-describedby="workspace-primary-description"
                            />
                        </div>
                        <div className="flex items-center gap-3 pb-1">
                            <span
                                className="size-8 rounded-lg border"
                                style={{
                                    backgroundColor: /^#[0-9a-fA-F]{6}$/.test(form.data.primary_color)
                                        ? form.data.primary_color
                                        : defaultWorkspaceColor,
                                }}
                                aria-label={`Colour preview ${form.data.primary_color}`}
                            />
                            <span className="text-muted-foreground text-sm">{form.data.primary_color.toUpperCase()}</span>
                        </div>
                    </div>
                    <p id="workspace-primary-description" className="text-muted-foreground text-xs">
                        Enter a six-digit hex colour, for example #230EF4.
                    </p>
                    <Errors errors={form.errors} />
                    {canUpdate && <Button disabled={form.processing}>Save workspace appearance</Button>}
                </fieldset>
            </form>
        </Panel>
    );
}
