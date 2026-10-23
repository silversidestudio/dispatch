import { Company, Contact, Field, Page, Pages } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
export default function List({
    company,
    list,
    contacts,
    eligible,
    canManage,
    history,
}: {
    company: Company;
    list: { id: number; name: string; description: string | null };
    contacts: Page<Contact>;
    eligible: number;
    canManage: boolean;
    history: { id: number; action: string; metadata: { contact_id?: number }; created_at: string }[];
}) {
    const base = `/organisations/${company.id}/lists/${list.id}`;
    const f = useForm({ name: list.name, description: list.description ?? '' });
    const search = useForm({ search: '' });
    const [ids, setIds] = useState<number[]>([]);
    return (
        <Workspace title={list.name} subtitle={`${eligible} current eligible recipients · Company: ${company.name}`}>
            <Panel title="List details">
                <form
                    className="flex flex-wrap items-end gap-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.patch(base);
                    }}
                >
                    <Field label="List name" value={f.data.name} change={(v) => f.setData('name', v)} />
                    <Field label="Description" value={f.data.description} change={(v) => f.setData('description', v)} />
                    {canManage && <Button>Save list</Button>}
                    <Errors errors={f.errors} />
                </form>
            </Panel>
            <Panel
                title="Manage membership"
                description="Search your company contacts, select a page or individual contacts, then add or remove membership."
            >
                <form
                    className="mb-4 flex items-end gap-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        search.get(base);
                    }}
                >
                    <Field label="Search contact email" value={search.data.search} change={(v) => search.setData('search', v)} />
                    <Button variant="outline">Search</Button>
                </form>
                {canManage && (
                    <div className="mb-4 flex gap-3">
                        <Button
                            disabled={!ids.length}
                            onClick={() => router.post(base + '/members', { ids, action: 'add' }, { onSuccess: () => setIds([]) })}
                        >
                            Add selected
                        </Button>
                        <Button
                            variant="outline"
                            disabled={!ids.length}
                            onClick={() => router.post(base + '/members', { ids, action: 'remove' }, { onSuccess: () => setIds([]) })}
                        >
                            Remove selected
                        </Button>
                    </div>
                )}
                <div className="overflow-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr>
                                <th className="p-3">
                                    <input
                                        type="checkbox"
                                        aria-label="Select page"
                                        onChange={(e) => setIds(e.target.checked ? contacts.data.map((c) => c.id) : [])}
                                    />
                                </th>
                                <th>Contact</th>
                                <th>Member</th>
                                <th>Eligibility</th>
                            </tr>
                        </thead>
                        <tbody>
                            {contacts.data.map((c) => (
                                <tr key={c.id} className="border-t">
                                    <td className="p-3">
                                        <input
                                            type="checkbox"
                                            aria-label={`Select ${c.email}`}
                                            checked={ids.includes(c.id)}
                                            onChange={(e) => setIds(e.target.checked ? [...ids, c.id] : ids.filter((id) => id !== c.id))}
                                        />
                                    </td>
                                    <td>
                                        <Link className="underline" href={`/organisations/${company.id}/contacts/${c.id}`}>
                                            {c.email}
                                        </Link>
                                    </td>
                                    <td>{c.member ? 'Yes' : 'No'}</td>
                                    <td>{c.eligibility}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pages page={contacts} />
            </Panel>
            <Panel title="Membership history">
                {history.map((h) => (
                    <p className="py-2 text-sm" key={h.id}>
                        {h.action} · contact {h.metadata.contact_id ?? '—'} · {new Date(h.created_at).toLocaleString()}
                    </p>
                ))}
            </Panel>
        </Workspace>
    );
}
