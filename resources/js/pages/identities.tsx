import { Company, Field } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
export type Identity = {
    id: number;
    name: string;
    email: string;
    reply_to: string | null;
    domain: string;
    verification_status: string;
    is_default: boolean;
};
export default function Identities({ company, identities, canManage }: { company: Company; identities: Identity[]; canManage: boolean }) {
    const f = useForm({ name: '', email: '', reply_to: '', is_default: false as boolean });
    const [id, setId] = useState<number | null>(null);
    const base = `/organisations/${company.id}/identities`;
    return (
        <Workspace title="Sending identities" subtitle="Simulation only. No DNS checks or real delivery are enabled.">
            <Panel title="Company identities">
                <div className="grid gap-4 md:grid-cols-2">
                    {identities.map((i) => (
                        <div key={i.id} className="rounded-xl border p-5">
                            <p className="font-medium">
                                {i.name} {i.is_default && '· Default'}
                            </p>
                            <p className="mt-1 text-sm">{i.email}</p>
                            <p className="text-muted-foreground mt-3 text-xs">{i.domain} · Unverified · SPF, DKIM and DMARC not checked</p>
                            {canManage && (
                                <Button
                                    className="mt-4"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        setId(i.id);
                                        f.setData({ name: i.name, email: i.email, reply_to: i.reply_to ?? '', is_default: i.is_default });
                                    }}
                                >
                                    Edit identity
                                </Button>
                            )}
                        </div>
                    ))}
                </div>
            </Panel>
            {canManage && (
                <Panel title={id ? 'Edit identity' : 'Add identity'}>
                    <form
                        className="max-w-xl space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            const options = {
                                onSuccess: () => {
                                    f.reset();
                                    setId(null);
                                },
                            };
                            if (id) f.patch(base + '/' + id, options);
                            else f.post(base, options);
                        }}
                    >
                        <Field label="Sender name" required value={f.data.name} change={(v) => f.setData('name', v)} />
                        <Field label="Sender email" type="email" required value={f.data.email} change={(v) => f.setData('email', v)} />
                        <Field label="Reply-to email" type="email" value={f.data.reply_to} change={(v) => f.setData('reply_to', v)} />
                        <label className="flex gap-2 text-sm">
                            <input type="checkbox" checked={f.data.is_default} onChange={(e) => f.setData('is_default', e.target.checked)} />
                            Default identity
                        </label>
                        <Errors errors={f.errors} />
                        <Button>Save unverified identity</Button>
                    </form>
                </Panel>
            )}
        </Workspace>
    );
}
