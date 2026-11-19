import { ConfirmDialog } from '@/components/confirm-dialog';
import { DeliveryStatus, EligibilityStatus } from '@/components/delivery-status';
import { Company, Contact, Field, Metrics, Page, Pages, Select } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { contactFieldLabel, eligibilityLabel } from '@/lib/labels';
import { Link, router, useForm } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';
export default function Contacts({
    company,
    contacts,
    filters,
    canManage,
    stats,
    view,
}: {
    company: Company;
    contacts: Page<Contact>;
    filters: { search?: string; eligibility?: string; sort?: string; view?: string };
    canManage: boolean;
    stats: Record<string, number>;
    view: 'active' | 'archived' | 'trash';
}) {
    const base = `/organisations/${company.id}`;
    const f = useForm({ email: '', first_name: '', last_name: '', company_name: '', telephone: '', source: 'Manual entry', status: 'active' });
    const search = useForm({
        search: filters.search ?? '',
        eligibility: filters.eligibility ?? '',
        sort: filters.sort ?? 'created_at',
        view,
    });
    const [ids, setIds] = useState<number[]>([]);
    const sectionTitle = { active: 'Active contacts', archived: 'Archived contacts', trash: 'Recently deleted contacts' }[view];
    const sectionDescription = {
        active: 'Contacts available in this workspace. Eligibility and suppression are checked separately before campaign sending.',
        archived: 'Archived contacts remain stored but are excluded from campaign audiences.',
        trash: 'Deleted contacts are hidden from normal use and can be restored. Suppression history is retained.',
    }[view];
    return (
        <Workspace
            title="Contacts"
            subtitle={`${company.name} · Viewing: ${{ active: 'Active contacts', archived: 'Archived contacts', trash: 'Recently deleted contacts' }[view]} · Eligibility shows whether a contact can be included in a campaign.`}
            actions={
                <Button variant="outline" asChild>
                    <Link href={base + '/imports'}>CSV imports</Link>
                </Button>
            }
        >
            <Metrics data={stats} />
            <Panel title={sectionTitle} description={sectionDescription}>
                <div className="mb-4 flex flex-wrap gap-2" role="group" aria-label="Contact view">
                    {(['active', 'archived', 'trash'] as const).map((tab) => (
                        <Button
                            key={tab}
                            size="sm"
                            variant={view === tab ? 'default' : 'outline'}
                            aria-pressed={view === tab}
                            className={view === tab ? 'ring-primary ring-2 ring-offset-2' : ''}
                            onClick={() => router.get(base + '/contacts', { ...search.data, view: tab }, { preserveState: true })}
                        >
                            {view === tab && <Check aria-hidden="true" />}
                            {{ active: 'Active', archived: 'Archived', trash: 'Recently deleted' }[tab]}
                        </Button>
                    ))}
                </div>
                <form
                    className="mb-5 grid gap-3 sm:grid-cols-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        search.get(base + '/contacts');
                    }}
                >
                    <Field label="Search contacts" value={search.data.search} change={(v) => search.setData('search', v)} />
                    <Select label="Eligibility" value={search.data.eligibility} change={(v) => search.setData('eligibility', v)}>
                        <option value="">All eligibility</option>
                        {[
                            'unknown',
                            'consent',
                            'documented_basis',
                            'csv_import',
                            'unsubscribed',
                            'hard_bounce',
                            'complaint',
                            'administratively_blocked',
                        ].map((v) => (
                            <option key={v} value={v}>
                                {eligibilityLabel(v)}
                            </option>
                        ))}
                    </Select>
                    <div className="flex items-end gap-2">
                        <Select label="Sort" value={search.data.sort} change={(v) => search.setData('sort', v)}>
                            {(['created_at', 'email', 'first_name'] as const).map((v) => (
                                <option key={v} value={v}>
                                    {{ created_at: 'Date added', email: 'Email address', first_name: 'First name' }[v]}
                                </option>
                            ))}
                        </Select>
                        <Button>Filter</Button>
                    </div>
                </form>
                {canManage && ids.length > 0 && (
                    <div className="mb-4 flex gap-3">
                        {view === 'active' && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(base + '/contacts/bulk', { ids, action: 'archive', view }, { onSuccess: () => setIds([]) })
                                }
                            >
                                Archive selected
                            </Button>
                        )}
                        {view === 'archived' && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(base + '/contacts/bulk', { ids, action: 'unarchive', view }, { onSuccess: () => setIds([]) })
                                }
                            >
                                Restore selected
                            </Button>
                        )}
                        {view !== 'trash' && (
                            <ConfirmDialog
                                trigger={<Button variant="outline">Delete selected</Button>}
                                title="Move contacts to Recently deleted?"
                                description="Campaign and suppression history will be retained. You can restore these contacts later."
                                confirmLabel="Move to Recently deleted"
                                onConfirm={() =>
                                    router.post(base + '/contacts/bulk', { ids, action: 'delete', view }, { onSuccess: () => setIds([]) })
                                }
                            />
                        )}
                        {view === 'trash' && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(base + '/contacts/bulk', { ids, action: 'restore', view }, { onSuccess: () => setIds([]) })
                                }
                            >
                                Restore selected
                            </Button>
                        )}
                        {view !== 'trash' && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(base + '/contacts/bulk', { ids, action: 'suppress', view }, { onSuccess: () => setIds([]) })
                                }
                            >
                                Suppress selected
                            </Button>
                        )}
                    </div>
                )}
                <div className="overflow-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b">
                                <th className="p-3">
                                    {canManage && (
                                        <input
                                            type="checkbox"
                                            aria-label="Select page"
                                            checked={!!contacts.data.length && contacts.data.every((c) => ids.includes(c.id))}
                                            onChange={(e) => setIds(e.target.checked ? contacts.data.map((c) => c.id) : [])}
                                        />
                                    )}
                                </th>
                                <th>Email</th>
                                <th>Name</th>
                                {view !== 'trash' && <th>Status</th>}
                                <th>Eligibility</th>
                                <th>Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            {contacts.data.map((c) => (
                                <tr key={c.id} className="border-b last:border-0">
                                    <td className="p-3">
                                        {canManage && (
                                            <input
                                                type="checkbox"
                                                aria-label={`Select ${c.email}`}
                                                checked={ids.includes(c.id)}
                                                onChange={(e) => setIds(e.target.checked ? [...ids, c.id] : ids.filter((id) => id !== c.id))}
                                            />
                                        )}
                                    </td>
                                    <td>
                                        <Link className="font-medium underline" href={`${base}/contacts/${c.id}`}>
                                            {c.email}
                                        </Link>
                                    </td>
                                    <td>
                                        {c.first_name} {c.last_name}
                                    </td>
                                    {view !== 'trash' && (
                                        <td>
                                            <DeliveryStatus status={c.status} />
                                        </td>
                                    )}
                                    <td>
                                        <EligibilityStatus status={c.eligibility} />
                                    </td>
                                    <td>{c.source}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {!contacts.data.length && <p className="text-muted-foreground p-10 text-center">No contacts match your filters.</p>}
                </div>
                <Pages page={contacts} />
            </Panel>
            {canManage && (
                <Panel title="Create contact" description="New contacts are not campaign-eligible until their eligibility is recorded.">
                    <form
                        className="grid gap-4 md:grid-cols-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            f.post(base + '/contacts');
                        }}
                    >
                        {(['email', 'first_name', 'last_name', 'company_name', 'telephone', 'source'] as const).map((k) => (
                            <Field
                                key={k}
                                label={contactFieldLabel(k)}
                                value={f.data[k]}
                                type={k === 'email' ? 'email' : 'text'}
                                required={['email', 'source'].includes(k)}
                                change={(v) => f.setData(k, v)}
                            />
                        ))}
                        <Errors errors={f.errors} />
                        <div>
                            <Button disabled={f.processing}>Create contact</Button>
                        </div>
                    </form>
                </Panel>
            )}
        </Workspace>
    );
}
