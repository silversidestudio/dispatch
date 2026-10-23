import { Company, Field, Select } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Errors, Panel, Workspace } from '@/components/workspace';
import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
type Rule = { field: string; value: string; key: string };
type Segment = { id: number; name: string; rules: Rule[]; total: number; eligible: number };
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
    const [preview, setPreview] = useState<{ total: number; eligible: number; sample: { id: number; email: string; eligibility: string }[] } | null>(
        null,
    );
    return (
        <Workspace title="Audiences" subtitle={`${company.name} · List membership never grants marketing eligibility.`}>
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
                    {preview && (
                        <div className="mt-5 rounded-xl border p-4 text-sm">
                            <p className="mb-3 font-medium">
                                Segment preview · {preview.total} matches · {preview.eligible} eligible
                            </p>
                            {preview.sample.map((c) => (
                                <p key={c.id} className="py-1">
                                    {c.email} · {c.eligibility}
                                </p>
                            ))}
                        </div>
                    )}
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
                <Panel title="Dynamic segments" description="All structured rules must match. Counts are evaluated from current company contacts.">
                    <div className="space-y-3">
                        {segments.map((s) => (
                            <div key={s.id} className="rounded-xl border p-4">
                                <p className="font-medium">{s.name}</p>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    {s.total} matches · {s.eligible} eligible
                                </p>
                                <p className="mt-2 text-xs">
                                    {s.rules.map((r) => `${r.field}${r.key ? ':' + r.key : ''} = ${r.value}`).join(' AND ')}
                                </p>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="mt-3 mr-2"
                                    onClick={() =>
                                        fetch(`${base}/segments/${s.id}/preview`, { headers: { Accept: 'application/json' } })
                                            .then((r) => (r.ok ? r.json() : null))
                                            .then(setPreview)
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
                                <div key={i} className="grid gap-2 rounded-xl border p-3">
                                    <Select
                                        label={`Rule ${i + 1} field`}
                                        value={r.field}
                                        change={(v) =>
                                            seg.setData(
                                                'rules',
                                                seg.data.rules.map((x, j) => (j === i ? { ...x, field: v } : x)),
                                            )
                                        }
                                    >
                                        {['status', 'source', 'company_name', 'list_id', 'created_after', 'created_before', 'attribute'].map((v) => (
                                            <option key={v}>{v}</option>
                                        ))}
                                    </Select>
                                    {r.field === 'list_id' ? (
                                        <Select
                                            label={`Rule ${i + 1} list`}
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
                                    ) : (
                                        <Field
                                            label={`Rule ${i + 1} value`}
                                            value={r.value}
                                            change={(v) =>
                                                seg.setData(
                                                    'rules',
                                                    seg.data.rules.map((x, j) => (j === i ? { ...x, value: v } : x)),
                                                )
                                            }
                                        />
                                    )}{' '}
                                    {r.field === 'attribute' && (
                                        <Field
                                            label="Attribute key"
                                            value={r.key}
                                            change={(v) =>
                                                seg.setData(
                                                    'rules',
                                                    seg.data.rules.map((x, j) => (j === i ? { ...x, key: v } : x)),
                                                )
                                            }
                                        />
                                    )}
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() =>
                                            seg.setData(
                                                'rules',
                                                seg.data.rules.filter((_, j) => j !== i),
                                            )
                                        }
                                    >
                                        Remove rule
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
                                    Add rule
                                </Button>
                                <Button>{edit ? 'Save segment' : 'Create segment'}</Button>
                            </div>
                            <Errors errors={seg.errors} />
                        </form>
                    )}
                </Panel>
            </div>
        </Workspace>
    );
}
