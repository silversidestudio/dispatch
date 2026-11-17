import { Company, Field, Select } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { readableLabel } from '@/lib/labels';
import { Link, useForm } from '@inertiajs/react';
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
}: {
    company: Company;
    lists: { id: number; name: string; description: string | null; contacts_count: number }[];
    segments: Segment[];
    canManage: boolean;
}) {
    const base = `/organisations/${company.id}`;
    const f = useForm({ name: '', description: '' });
    const seg = useForm({ name: '', rules: [{ field: 'status', value: 'active', key: '' }] as Rule[] });
    const [edit, setEdit] = useState<number | null>(null);
    const [search, setSearch] = useState('');
    const [preview, setPreview] = useState<SegmentPreview | null>(null);
    return (
        <Workspace title="Audiences" subtitle={`${company.name} · Lists organise contacts; eligibility is managed on each contact.`}>
            <div className="grid gap-6 lg:grid-cols-2">
                <Panel title="Mailing lists">
                    <Field label="Filter lists" value={search} change={setSearch} />
                    <div className="mt-4 space-y-4">
                        {lists
                            .filter((l) => l.name.toLowerCase().includes(search.toLowerCase()))
                            .map((l) => (
                                <Link href={`${base}/lists/${l.id}`} key={l.id} className="block rounded-xl border p-4">
                                    <p className="font-medium">
                                        {l.name}
                                        <span className="text-muted-foreground float-right text-sm">{l.contacts_count} contacts</span>
                                    </p>
                                    <p className="text-muted-foreground mt-1 text-sm">{l.description}</p>
                                </Link>
                            ))}
                    </div>
                    {canManage && (
                        <form
                            className="mt-5 space-y-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                f.post(base + '/lists', { onSuccess: () => f.reset() });
                            }}
                        >
                            <Field label="List name" required value={f.data.name} change={(v) => f.setData('name', v)} />
                            <Field label="List description" value={f.data.description} change={(v) => f.setData('description', v)} />
                            <Errors errors={f.errors} />
                            <Button>Create list</Button>
                        </form>
                    )}
                </Panel>
                <Panel
                    title="Dynamic segments"
                    description="Choose conditions to build an audience that updates as contact details change. Every condition must match. Marketing eligibility is checked separately."
                >
                    <div className="space-y-3">
                        {segments.map((s) => (
                            <div key={s.id} className="rounded-xl border p-4">
                                <p className="font-medium">{s.name}</p>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    {s.total} {s.total === 1 ? 'contact matches' : 'contacts match'} · {s.eligible} eligible to email
                                </p>
                                <div className="mt-3 flex flex-wrap items-center gap-2" aria-label="Segment conditions">
                                    {s.rules.map((rule, index) => (
                                        <span key={`${rule.field}-${index}`} className="contents">
                                            {index > 0 && <span className="text-muted-foreground text-xs">and</span>}
                                            <span className="bg-muted rounded-md px-2 py-1 text-xs">{conditionSummary(rule, lists)}</span>
                                        </span>
                                    ))}
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="mt-3 mr-2"
                                    onClick={() =>
                                        fetch(`${base}/segments/${s.id}/preview`, { headers: { Accept: 'application/json' } })
                                            .then((r) => (r.ok ? r.json() : null))
                                            .then((result) => (result ? setPreview({ ...result, segmentId: s.id, segmentName: s.name }) : null))
                                    }
                                >
                                    Preview segment
                                </Button>
                                {canManage && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="mt-3"
                                        onClick={() => {
                                            setEdit(s.id);
                                            seg.setData({ name: s.name, rules: s.rules });
                                        }}
                                    >
                                        Edit segment
                                    </Button>
                                )}
                            </div>
                        ))}
                    </div>
                    {canManage && (
                        <form
                            className="mt-5 space-y-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                            setPreview(null);
                                const opt = {
                                    onSuccess: () => {
                                        seg.reset();
                                        setEdit(null);
                                    },
                                };
                                if (edit) seg.patch(`${base}/segments/${edit}`, opt);
                                else seg.post(base + '/segments', opt);
                            }}
                        >
                            <Field label="Segment name" required value={seg.data.name} change={(v) => seg.setData('name', v)} />
                            {seg.data.rules.map((r, i) => (
                                <div key={i} className="space-y-3 rounded-xl border p-4">
                                    <p className="text-sm font-medium">Condition {i + 1}</p>
                                    <Select
                                        label="Contact field"
                                        value={r.field}
                                        change={(v) => {
                                            const defaultValue = v === 'status' ? 'active' : '';
                                            seg.setData(
                                                'rules',
                                                seg.data.rules.map((x, j) => (j === i ? { ...x, field: v, value: defaultValue, key: '' } : x)),
                                            );
                                        }}
                                    >
                                        {segmentFields.map(({ value, label }) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ))}
                                    </Select>
                                    {r.field === 'status' ? (
                                        <Select
                                            label="Contact status"
                                            value={r.value}
                                            change={(v) =>
                                                seg.setData('rules', seg.data.rules.map((x, j) => (j === i ? { ...x, value: v } : x)))
                                            }
                                        >
                                            <option value="active">Active</option>
                                            <option value="archived">Archived</option>
                                        </Select>
                                    ) : null}
                                    {r.field === 'list_id' ? (
                                        <Select
                                            label="Mailing list"
                                            value={r.value}
                                            change={(v) =>
                                                seg.setData(
                                                    'rules',
                                                    seg.data.rules.map((x, j) => (j === i ? { ...x, value: v } : x)),
                                                )
                                            }
                                        >
                                            <option value="">Choose list</option>
                                            {lists.map((l) => (
                                                <option key={l.id} value={l.id}>
                                                    {l.name}
                                                </option>
                                            ))}
                                        </Select>
                                    ) : null}
                                    {['source', 'company_name'].includes(r.field) ? (
                                        <Field
                                            label={r.field === 'source' ? 'Source name' : 'Company name'}
                                            value={r.value}
                                            change={(v) =>
                                                seg.setData(
                                                    'rules',
                                                    seg.data.rules.map((x, j) => (j === i ? { ...x, value: v } : x)),
                                                )
                                            }
                                        />
                                    ) : null}
                                    {['created_after', 'created_before'].includes(r.field) ? (
                                        <Field
                                            label={r.field === 'created_after' ? 'Created on or after' : 'Created on or before'}
                                            type="date"
                                            value={r.value}
                                            change={(v) =>
                                                seg.setData('rules', seg.data.rules.map((x, j) => (j === i ? { ...x, value: v } : x)))
                                            }
                                        />
                                    ) : null}
                                    {r.field === 'attribute' && (
                                        <Field
                                            label="Custom field name"
                                            value={r.key}
                                            change={(v) =>
                                                seg.setData(
                                                    'rules',
                                                    seg.data.rules.map((x, j) => (j === i ? { ...x, key: v } : x)),
                                                )
                                            }
                                        />
                                    )}
                                    {r.field === 'attribute' && (
                                        <Field
                                            label="Custom field value"
                                            value={r.value}
                                            change={(v) =>
                                                seg.setData('rules', seg.data.rules.map((x, j) => (j === i ? { ...x, value: v } : x)))
                                            }
                                        />
                                    )}
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            seg.setData(
                                                'rules',
                                                seg.data.rules.filter((_, j) => j !== i),
                                            )
                                        }
                                    >
                                        Remove condition
                                    </Button>
                                </div>
                            ))}
                            <div className="flex gap-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={seg.data.rules.length >= 8}
                                    onClick={() => seg.setData('rules', [...seg.data.rules, { field: 'source', value: '', key: '' }])}
                                >
                                    Add condition
                                </Button>
                                <Button>{edit ? 'Save segment' : 'Create segment'}</Button>
                            </div>
                            <Errors errors={seg.errors} />
                        </form>
                    )}
                    {preview && (
                        <div className="mt-5 rounded-xl border p-4 text-sm" aria-live="polite">
                            <p className="font-medium">Preview: {preview.segmentName}</p>
                            <p className="text-muted-foreground mt-1">
                                {preview.total} {preview.total === 1 ? 'contact matches' : 'contacts match'} · {preview.eligible} eligible to email
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
            </div>
        </Workspace>
    );
}
