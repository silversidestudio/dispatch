import { ConfirmDialog } from '@/components/confirm-dialog';
import { Company, Field, Select } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { readableLabel } from '@/lib/labels';
import { Link, router, useForm } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';
type Rule = { field: string; value: string; key: string };
type Segment = { id: number; name: string; rules: Rule[]; total: number; eligible: number };
type SegmentPreview = {
    segmentId: number;
    segmentName: string;
    total: number;
    eligible: number;
    sample: { id: number; email: string; eligibility: string }[];
};

const segmentFields = [
    { value: 'status', label: 'Contact status' },
    { value: 'source', label: 'Source' },
    { value: 'company_name', label: 'Company name' },
    { value: 'list_id', label: 'Mailing list' },
    { value: 'created_after', label: 'Created on or after' },
    { value: 'created_before', label: 'Created on or before' },
    { value: 'attribute', label: 'Custom attribute' },
];

function conditionSummary(rule: Rule, lists: { id: number; name: string }[]): string {
    switch (rule.field) {
        case 'status':
            return `Status is ${rule.value === 'active' ? 'Active' : 'Archived'}`;
        case 'source':
            return `Source is “${rule.value}”`;
        case 'company_name':
            return `Company name is “${rule.value}”`;
        case 'list_id':
            return `In the “${lists.find((list) => String(list.id) === rule.value)?.name ?? 'selected'}” list`;
        case 'created_after':
            return `Created on or after ${rule.value}`;
        case 'created_before':
            return `Created on or before ${rule.value}`;
        case 'attribute':
            return `Custom field “${rule.key}” is “${rule.value}”`;
        default:
            return 'Contact matches this condition';
    }
}

export default function Audiences({
    company,
    lists,
    segments,
    canManage,
    view,
}: {
    company: Company;
    lists: {
        id: number;
        name: string;
        description: string | null;
        contacts_count: number;
        deleted_at?: string | null;
        archived_at?: string | null;
        delete_block_reason?: string | null;
    }[];
    segments: Segment[];
    canManage: boolean;
    view: 'active' | 'archived' | 'trash';
}) {
    const base = `/organisations/${company.id}`;
    const f = useForm({ name: '', description: '' });
    const seg = useForm({ name: '', rules: [{ field: 'status', value: 'active', key: '' }] as Rule[] });
    const [edit, setEdit] = useState<number | null>(null);
    const [search, setSearch] = useState('');
    const [preview, setPreview] = useState<SegmentPreview | null>(null);
    const filteredLists = lists.filter((list) => list.name.toLowerCase().includes(search.toLowerCase()));
    return (
        <Workspace
            title="Audiences"
            subtitle={`${company.name} · Viewing: ${{ active: 'Active mailing lists', archived: 'Archived mailing lists', trash: 'Recently deleted mailing lists' }[view]} · Eligibility is managed on each contact.`}
        >
            <div className="grid gap-6 lg:grid-cols-2">
                <div className="space-y-6">
                    <Panel
                        title={{ active: 'Active mailing lists', archived: 'Archived mailing lists', trash: 'Recently deleted mailing lists' }[view]}
                        description={
                            {
                                active: 'Current lists available when building a campaign audience.',
                                archived: 'Retired lists kept for reference; they are unavailable to new campaign audiences.',
                                trash: 'Deleted lists can be restored. Contact memberships are retained.',
                            }[view]
                        }
                    >
                        <div className="mb-4 flex flex-wrap gap-2" role="group" aria-label="Mailing list view">
                            {(['active', 'archived', 'trash'] as const).map((tab) => (
                                <Button
                                    key={tab}
                                    size="sm"
                                    variant={view === tab ? 'default' : 'outline'}
                                    aria-pressed={view === tab}
                                    className={view === tab ? 'ring-primary ring-2 ring-offset-2' : ''}
                                    onClick={() => router.get(`${base}/audiences`, { view: tab }, { preserveState: true })}
                                >
                                    {view === tab && <Check aria-hidden="true" />}
                                    {{ active: 'Active', archived: 'Archived', trash: 'Recently deleted' }[tab]}
                                </Button>
                            ))}
                        </div>
                        {view !== 'trash' && <Field label="Filter lists" value={search} change={setSearch} />}
                        <div className="mt-4 space-y-3">
                            {filteredLists.map((list) => (
                                <div key={list.id} className="rounded-xl border p-4">
                                    {view === 'trash' ? (
                                        <p className="font-medium">{list.name}</p>
                                    ) : (
                                        <Link href={`${base}/lists/${list.id}`} className="font-medium underline">
                                            {list.name}
                                        </Link>
                                    )}
                                    {view !== 'trash' && (
                                        <span className="text-muted-foreground float-right text-sm">{list.contacts_count} contacts</span>
                                    )}
                                    <p className="text-muted-foreground mt-1 text-sm">{list.description || 'No description'}</p>
                                    {canManage && (
                                        <div className="mt-3 flex gap-2">
                                            {view === 'active' && (
                                                <>
                                                    <ConfirmDialog
                                                        trigger={
                                                            <Button size="sm" variant="outline">
                                                                Archive
                                                            </Button>
                                                        }
                                                        title={`Archive “${list.name}”?`}
                                                        description="Contacts will stay in the company. You can restore this list later."
                                                        confirmLabel="Archive list"
                                                        confirmVariant="default"
                                                        onConfirm={() => router.post(`${base}/lists/${list.id}/archive`)}
                                                    />
                                                </>
                                            )}
                                            {view !== 'active' && (
                                                <Button size="sm" variant="outline" onClick={() => router.post(`${base}/lists/${list.id}/restore`)}>
                                                    Restore
                                                </Button>
                                            )}
                                            {view !== 'trash' &&
                                                (list.delete_block_reason ? (
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <Button size="sm" variant="outline" disabled title={list.delete_block_reason}>
                                                            Delete unavailable
                                                        </Button>
                                                        <span className="text-muted-foreground text-xs">{list.delete_block_reason}</span>
                                                    </div>
                                                ) : (
                                                    <ConfirmDialog
                                                        trigger={
                                                            <Button size="sm" variant="outline">
                                                                Delete
                                                            </Button>
                                                        }
                                                        title={`Move “${list.name}” to Recently deleted?`}
                                                        description="The list will be removed from active and archived views. Contacts and campaign history will be retained."
                                                        confirmLabel="Move to Recently deleted"
                                                        onConfirm={() => router.delete(`${base}/lists/${list.id}`)}
                                                    />
                                                ))}
                                        </div>
                                    )}
                                </div>
                            ))}
                            {filteredLists.length === 0 && (
                                <p className="text-muted-foreground rounded-xl border border-dashed p-6 text-center text-sm">
                                    {lists.length ? 'No lists match your filter.' : 'No mailing lists yet.'}
                                </p>
                            )}
                        </div>
                    </Panel>
                    {canManage && view === 'active' && (
                        <Panel
                            title="Create a mailing list"
                            description="Give the list a clear name so it’s easy to recognise when choosing a campaign audience."
                        >
                            <form
                                className="space-y-4"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    f.post(base + '/lists', { onSuccess: () => f.reset() });
                                }}
                            >
                                <Field label="List name" required value={f.data.name} change={(v) => f.setData('name', v)} />
                                <Field label="List description" value={f.data.description} change={(v) => f.setData('description', v)} />
                                <Errors errors={f.errors} />
                                <Button disabled={f.processing}>Create list</Button>
                            </form>
                        </Panel>
                    )}
                </div>
                <div className="space-y-6">
                    <Panel
                        title="Dynamic segments"
                        description="Segments update as contact details change. Every condition must match; marketing eligibility is checked separately."
                    >
                        <div className="space-y-3">
                            {segments.map((segment) => (
                                <div key={segment.id} className="rounded-xl border p-4">
                                    <p className="font-medium">{segment.name}</p>
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        {segment.total} {segment.total === 1 ? 'contact matches' : 'contacts match'} · {segment.eligible} eligible to
                                        email
                                    </p>
                                    <div className="mt-3 flex flex-wrap items-center gap-2" aria-label="Segment conditions">
                                        {segment.rules.map((rule, index) => (
                                            <span key={`${rule.field}-${index}`} className="contents">
                                                {index > 0 && <span className="text-muted-foreground text-xs">and</span>}
                                                <span className="bg-muted rounded-md px-2 py-1 text-xs">{conditionSummary(rule, lists)}</span>
                                            </span>
                                        ))}
                                    </div>
                                    <div className="mt-4 flex flex-wrap gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                fetch(`${base}/segments/${segment.id}/preview`, { headers: { Accept: 'application/json' } })
                                                    .then((response) => (response.ok ? response.json() : null))
                                                    .then((result) =>
                                                        result ? setPreview({ ...result, segmentId: segment.id, segmentName: segment.name }) : null,
                                                    )
                                            }
                                        >
                                            Preview segment
                                        </Button>
                                        {canManage && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => {
                                                    setEdit(segment.id);
                                                    seg.setData({ name: segment.name, rules: segment.rules });
                                                }}
                                            >
                                                Edit segment
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ))}
                            {segments.length === 0 && (
                                <p className="text-muted-foreground rounded-xl border border-dashed p-6 text-center text-sm">
                                    No dynamic segments yet.
                                </p>
                            )}
                        </div>
                        {preview && (
                            <div className="mt-4 rounded-xl border p-4 text-sm" aria-live="polite">
                                <p className="font-medium">Preview: {preview.segmentName}</p>
                                <p className="text-muted-foreground mt-1">
                                    {preview.total} {preview.total === 1 ? 'contact matches' : 'contacts match'} · {preview.eligible} eligible to
                                    email
                                </p>
                                {preview.sample.length > 0 && (
                                    <ul className="mt-3 divide-y">
                                        {preview.sample.map((contact) => (
                                            <li key={contact.id} className="flex flex-wrap justify-between gap-2 py-2">
                                                <span>{contact.email}</span>
                                                <span className="text-muted-foreground">{readableLabel(contact.eligibility)}</span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {preview.total === 0 && <p className="text-muted-foreground mt-3">No contacts match these conditions yet.</p>}
                            </div>
                        )}
                    </Panel>
                    {canManage && (
                        <Panel
                            title={edit ? `Edit segment: ${seg.data.name}` : 'Create a dynamic segment'}
                            description="Name the segment, then add the conditions that contacts must match."
                        >
                            <form
                                className="space-y-4"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    setPreview(null);
                                    const options = {
                                        onSuccess: () => {
                                            seg.reset();
                                            setEdit(null);
                                        },
                                    };
                                    if (edit) seg.patch(`${base}/segments/${edit}`, options);
                                    else seg.post(base + '/segments', options);
                                }}
                            >
                                <Field label="Segment name" required value={seg.data.name} change={(v) => seg.setData('name', v)} />
                                {seg.data.rules.map((rule, index) => (
                                    <div key={index} className="space-y-3 rounded-xl border p-4">
                                        <p className="text-sm font-medium">Condition {index + 1}</p>
                                        <Select
                                            label="Contact field"
                                            value={rule.field}
                                            change={(value) => {
                                                const defaultValue = value === 'status' ? 'active' : '';
                                                seg.setData(
                                                    'rules',
                                                    seg.data.rules.map((item, itemIndex) =>
                                                        itemIndex === index ? { ...item, field: value, value: defaultValue, key: '' } : item,
                                                    ),
                                                );
                                            }}
                                        >
                                            {segmentFields.map(({ value, label }) => (
                                                <option key={value} value={value}>
                                                    {label}
                                                </option>
                                            ))}
                                        </Select>
                                        {rule.field === 'status' && (
                                            <Select
                                                label="Contact status"
                                                value={rule.value}
                                                change={(value) =>
                                                    seg.setData(
                                                        'rules',
                                                        seg.data.rules.map((item, itemIndex) => (itemIndex === index ? { ...item, value } : item)),
                                                    )
                                                }
                                            >
                                                <option value="active">Active</option>
                                                <option value="archived">Archived</option>
                                            </Select>
                                        )}
                                        {rule.field === 'list_id' && (
                                            <Select
                                                label="Mailing list"
                                                value={rule.value}
                                                change={(value) =>
                                                    seg.setData(
                                                        'rules',
                                                        seg.data.rules.map((item, itemIndex) => (itemIndex === index ? { ...item, value } : item)),
                                                    )
                                                }
                                            >
                                                <option value="">Choose list</option>
                                                {lists.map((list) => (
                                                    <option key={list.id} value={list.id}>
                                                        {list.name}
                                                    </option>
                                                ))}
                                            </Select>
                                        )}
                                        {['source', 'company_name'].includes(rule.field) && (
                                            <Field
                                                label={rule.field === 'source' ? 'Source name' : 'Company name'}
                                                value={rule.value}
                                                change={(value) =>
                                                    seg.setData(
                                                        'rules',
                                                        seg.data.rules.map((item, itemIndex) => (itemIndex === index ? { ...item, value } : item)),
                                                    )
                                                }
                                            />
                                        )}
                                        {['created_after', 'created_before'].includes(rule.field) && (
                                            <Field
                                                label={rule.field === 'created_after' ? 'Created on or after' : 'Created on or before'}
                                                type="date"
                                                value={rule.value}
                                                change={(value) =>
                                                    seg.setData(
                                                        'rules',
                                                        seg.data.rules.map((item, itemIndex) => (itemIndex === index ? { ...item, value } : item)),
                                                    )
                                                }
                                            />
                                        )}
                                        {rule.field === 'attribute' && (
                                            <>
                                                <Field
                                                    label="Custom field name"
                                                    value={rule.key}
                                                    change={(value) =>
                                                        seg.setData(
                                                            'rules',
                                                            seg.data.rules.map((item, itemIndex) =>
                                                                itemIndex === index ? { ...item, key: value } : item,
                                                            ),
                                                        )
                                                    }
                                                />
                                                <Field
                                                    label="Custom field value"
                                                    value={rule.value}
                                                    change={(value) =>
                                                        seg.setData(
                                                            'rules',
                                                            seg.data.rules.map((item, itemIndex) =>
                                                                itemIndex === index ? { ...item, value } : item,
                                                            ),
                                                        )
                                                    }
                                                />
                                            </>
                                        )}
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                seg.setData(
                                                    'rules',
                                                    seg.data.rules.filter((_, ruleIndex) => ruleIndex !== index),
                                                )
                                            }
                                        >
                                            Remove condition
                                        </Button>
                                    </div>
                                ))}
                                <div className="flex flex-wrap gap-3">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={seg.data.rules.length >= 8}
                                        onClick={() => seg.setData('rules', [...seg.data.rules, { field: 'source', value: '', key: '' }])}
                                    >
                                        Add condition
                                    </Button>
                                    <Button disabled={seg.processing}>{edit ? 'Save segment' : 'Create segment'}</Button>
                                    {edit && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={() => {
                                                seg.reset();
                                                setEdit(null);
                                            }}
                                        >
                                            Cancel editing
                                        </Button>
                                    )}
                                </div>
                                <Errors errors={seg.errors} />
                            </form>
                        </Panel>
                    )}
                </div>
            </div>
        </Workspace>
    );
}
