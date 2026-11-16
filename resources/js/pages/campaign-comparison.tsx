import { Company, Field, Page, Pages } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Panel, Workspace } from '@/components/workspace';
import { modeLabel } from '@/lib/labels';
import { Link, useForm } from '@inertiajs/react';
import { Analytics } from './campaign-analytics';
export default function Comparison({
    company,
    campaigns,
    filters,
}: {
    company: Company;
    campaigns: Page<{ id: number; name: string; scheduled_at: string | null; status: string; analytics: Analytics }>;
    filters: { from?: string; to?: string };
}) {
    const f = useForm({ from: filters.from ?? '', to: filters.to ?? '' });
    return (
        <Workspace title="Campaign comparison" subtitle="Compare campaign results. Simulations and unavailable rates are marked N/A.">
            <Panel title="Campaign performance">
                <form
                    className="mb-5 flex flex-wrap items-end gap-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        f.get(`/organisations/${company.id}/campaign-comparison`);
                    }}
                >
                    <Field label="Send date from" type="date" value={f.data.from} change={(v) => f.setData('from', v)} />
                    <Field label="Send date to" type="date" value={f.data.to} change={(v) => f.setData('to', v)} />
                    <Button>Filter campaigns</Button>
                </form>
                <div className="overflow-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr>
                                {[
                                    'Campaign',
                                    'Send date',
                                    'Audience',
                                    'Delivery rate',
                                    'Bounce rate',
                                    'Tracked open rate',
                                    'Tracked click rate',
                                    'Unsubscribe rate',
                                ].map((k) => (
                                    <th key={k} className="p-3">
                                        {k}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {campaigns.data.map((c) => (
                                <tr key={c.id} className="border-t">
                                    <td className="p-3">
                                        <Link className="underline" href={`/organisations/${company.id}/campaigns/${c.id}/analytics`}>
                                            {c.name}
                                        </Link>
                                        <p className="text-muted-foreground text-xs">{modeLabel(c.analytics.mode)}</p>
                                    </td>
                                    <td>{c.scheduled_at ? new Date(c.scheduled_at).toLocaleDateString() : '—'}</td>
                                    <td>{c.analytics.counts.original_audience}</td>
                                    {['delivery', 'bounce', 'unique_open', 'unique_click', 'unsubscribe'].map((k) => (
                                        <td key={k}>{c.analytics.rates[k] === null ? 'N/A' : c.analytics.rates[k] + '%'}</td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pages page={campaigns} />
            </Panel>
        </Workspace>
    );
}
