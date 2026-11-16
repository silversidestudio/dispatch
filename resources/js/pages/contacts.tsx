import { DeliveryStatus, EligibilityStatus } from '@/components/delivery-status';
import { Company, Contact, Field, Metrics, Page, Pages, Select } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { contactFieldLabel, eligibilityLabel } from '@/lib/labels';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
export default function Contacts({
    company,
    contacts,
    filters,
    canManage,
    stats,
}: {
    company: Company;
    contacts: Page<Contact>;
    filters: { search?: string; status?: string; eligibility?: string; sort?: string };
    canManage: boolean;
    stats: Record<string, number>;
}) {
    const base = `/organisations/${company.id}`;
    const f = useForm({ email: '', first_name: '', last_name: '', company_name: '', telephone: '', source: 'Manual entry', status: 'active' });
    const search = useForm({
        search: filters.search ?? '',
        status: filters.status ?? '',
        eligibility: filters.eligibility ?? '',
        sort: filters.sort ?? 'created_at',
    });
    const [ids, setIds] = useState<number[]>([]);
    return (
        <Workspace
            title="Contacts"
            subtitle={`${company.name} · Storing a contact does not establish marketing permission.`}
            actions={
                <Button variant="outline" asChild>
                    <Link href={base + '/imports'}>CSV imports</Link>
                </Button>
            }
        >
            <Metrics data={stats} />
            <Panel title="Contact directory">
                <form
                    className="mb-5 grid gap-3 sm:grid-cols-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        search.get(base + '/contacts');
                    }}
                >
                    <Field label="Search contacts" value={search.data.search} change={(v) => search.setData('search', v)} />
                    <Select label="Contact status" value={search.data.status} change={(v) => search.setData('status', v)}>
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="archived">Archived</option>
                    </Select>
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
                        <Button
                            variant="outline"
                            onClick={() => router.post(base + '/contacts/bulk', { ids, action: 'archive' }, { onSuccess: () => setIds([]) })}
                        >
                            Archive selected
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => router.post(base + '/contacts/bulk', { ids, action: 'suppress' }, { onSuccess: () => setIds([]) })}
                        >
                            Suppress selected
                        </Button>
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
                                <th>Status</th>
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
                                    <td>
                                        <DeliveryStatus status={c.status} />
                                    </td>
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
                <Panel title="Create contact" description="New contacts start with unknown eligibility.">
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
