import { DeliveryStatus } from '@/components/delivery-status';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { readableLabel } from '@/lib/labels';
import { Link, router, useForm } from '@inertiajs/react';
import { Building2, Plus } from 'lucide-react';
import { useState } from 'react';
export default function Organisations({
    organisationList: organisations,
    canCreate,
}: {
    canCreate: boolean;
    organisationList: { id: number; name: string; role: string; status: string }[];
}) {
    const f = useForm({ name: '' });
    const [search, setSearch] = useState('');
    return (
        <Workspace title="Workspaces" subtitle="Switch between the companies you belong to.">
            <div className="grid gap-6 lg:grid-cols-[1.6fr_1fr]">
                <Panel title="Your workspaces">
                    <Input
                        aria-label="Search organisations"
                        placeholder="Search workspaces…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                    <div className="mt-4 space-y-3">
                        {organisations
                            .filter((o) => o.name.toLowerCase().includes(search.toLowerCase()))
                            .map((o) => (
                                <div key={o.id} className="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4">
                                    <div className="flex items-center gap-3">
                                        <Building2 className="size-5 text-indigo-500" />
                                        <div>
                                            <Link href={`/organisations/${o.id}`} className="text-sm font-medium hover:underline">
                                                {o.name}
                                            </Link>
                                            <p className="text-muted-foreground mt-1 text-xs capitalize">
                                                {readableLabel(o.role)} <DeliveryStatus status={o.status} />
                                            </p>
                                        </div>
                                    </div>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        disabled={o.status !== 'active'}
                                        onClick={() => router.post(`/organisations/${o.id}/switch`)}
                                    >
                                        Open workspace
                                    </Button>
                                </div>
                            ))}
                        {!organisations.length && (
                            <p className="text-muted-foreground py-10 text-center text-sm">
                                No workspaces are linked to this account yet. A platform administrator can invite you to one.
                            </p>
                        )}
                    </div>
                </Panel>
                {canCreate && (
                    <Panel title="Create an organisation" description="You’ll be the owner of this workspace.">
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                f.post('/organisations');
                            }}
                            className="space-y-4"
                        >
                            <div className="space-y-2">
                                <Label htmlFor="organisation-name">Organisation name</Label>
                                <Input
                                    id="organisation-name"
                                    value={f.data.name}
                                    onChange={(e) => f.setData('name', e.target.value)}
                                    required
                                    maxLength={120}
                                    placeholder="e.g. Acorn Studio"
                                />
                            </div>
                            <Errors errors={f.errors} />
                            <Button disabled={f.processing}>
                                <Plus className="size-4" />
                                Create organisation
                            </Button>
                        </form>
                    </Panel>
                )}
            </div>
        </Workspace>
    );
}
