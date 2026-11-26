import { EligibilityStatus } from '@/components/delivery-status';
import { Company, Contact, Field, Page, Pages } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { readableLabel } from '@/lib/labels';
import { Link, router, useForm } from '@inertiajs/react';
import { Check, Minus, Search, UserMinus, UserPlus } from 'lucide-react';
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
        <Workspace title={list.name} subtitle={`${eligible} currently eligible contacts · ${company.name}`}>
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
                description="Being on this list does not override a contact’s eligibility or suppression status."
            >
                <form
                    className="mb-4 flex flex-wrap items-end gap-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        search.get(base);
                    }}
                >
                    <Field label="Search contact email" value={search.data.search} change={(v) => search.setData('search', v)} />
                    <Button variant="outline">
                        <Search aria-hidden="true" />
                        Search
                    </Button>
                </form>
                {canManage && (
                    <div className="mb-4 flex gap-3">
                        <Button
                            disabled={!ids.length}
                            onClick={() => router.post(base + '/members', { ids, action: 'add' }, { onSuccess: () => setIds([]) })}
                        >
                            <UserPlus aria-hidden="true" />
                            Add selected
                        </Button>
                        <Button
                            variant="outline"
                            disabled={!ids.length}
                            onClick={() => router.post(base + '/members', { ids, action: 'remove' }, { onSuccess: () => setIds([]) })}
                        >
                            <UserMinus aria-hidden="true" />
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
                                        checked={contacts.data.length > 0 && contacts.data.every((c) => ids.includes(c.id))}
                                        onChange={(e) => setIds(e.target.checked ? contacts.data.map((c) => c.id) : [])}
                                    />
                                </th>
                                <th>Contact</th>
                                <th>Member</th>
                                <th className="hidden sm:table-cell">Eligibility</th>
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
                                        <Link className="break-all underline sm:break-normal" href={`/organisations/${company.id}/contacts/${c.id}`}>
                                            {c.email}
                                        </Link>
                                        <div className="mt-1.5 sm:hidden">
                                            <EligibilityStatus status={c.eligibility} />
                                        </div>
                                    </td>
                                    <td className="py-2 pr-3">
                                        {c.member ? (
                                            <span
                                                className="inline-flex size-7 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                title="Included in list"
                                            >
                                                <Check aria-hidden="true" className="size-4" />
                                                <span className="sr-only">Included in list</span>
                                            </span>
                                        ) : (
                                            <span
                                                className="text-muted-foreground inline-flex size-7 items-center justify-center"
                                                title="Not in list"
                                            >
                                                <Minus aria-hidden="true" className="size-4" />
                                                <span className="sr-only">Not in list</span>
                                            </span>
                                        )}
                                    </td>
                                    <td className="hidden sm:table-cell">
                                        <EligibilityStatus status={c.eligibility} />
                                    </td>
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
                        {h.action === 'list.membership_add'
                            ? 'Added to list'
                            : h.action === 'list.membership_remove'
                              ? 'Removed from list'
                              : readableLabel(h.action)}{' '}
                        · contact {h.metadata.contact_id ?? '—'} · {new Date(h.created_at).toLocaleString()}
                    </p>
                ))}
            </Panel>
        </Workspace>
    );
}
