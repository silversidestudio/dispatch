import { DeliveryStatus } from '@/components/delivery-status';
import { Company, Page, Pages } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Panel, Workspace } from '@/components/workspace';
import { modeLabel } from '@/lib/labels';
import { Link, router } from '@inertiajs/react';
import { Mail, Plus } from 'lucide-react';
export type Campaign = {
    delivery_mode: string;
    open_tracking: boolean;
    click_tracking: boolean;
    id: number;
    name: string;
    subject: string;
    preview_text: string | null;
    sending_identity_id: number;
    reply_to: string | null;
    audience_type: string;
    audience_id: number;
    html: string;
    text_body: string;
    status: string;
    scheduled_at: string | null;
    completed_at?: string | null;
    snapshot: Record<string, unknown> | null;
    archived_at?: string | null;
};
export default function Campaigns({
    company,
    campaigns,
    canManage,
    view,
}: {
    company: Company;
    campaigns: Page<Campaign>;
    canManage: boolean;
    view: 'active' | 'archived' | 'trash';
}) {
    const base = `/organisations/${company.id}/campaigns`;
    return (
        <Workspace
            title="Campaigns"
            subtitle={`${company.name} · Drafts, scheduled sends and campaign history.`}
            actions={
                canManage && (
                    <Button asChild>
                        <Link href={base + '/create'}>
                            <Plus aria-hidden="true" />
                            Create campaign
                        </Link>
                    </Button>
                )
            }
        >
            <Panel title="Campaign history">
                <div className="mb-4 flex flex-wrap gap-2" role="group" aria-label="Campaign view">
                    {(['active', 'archived', 'trash'] as const).map((tab) => (
                        <Button
                            key={tab}
                            size="sm"
                            variant={view === tab ? 'default' : 'outline'}
                            onClick={() => router.get(base, { view: tab }, { preserveState: true })}
                        >
                            {{ active: 'Active', archived: 'Archived', trash: 'Recently deleted' }[tab]}
                        </Button>
                    ))}
                </div>
                <div className="overflow-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr>
                                {['Campaign', 'Subject', 'Status', 'Scheduled', ...(canManage ? ['Actions'] : [])].map((s) => (
                                    <th key={s} className="p-3">
                                        {s}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {campaigns.data.map((c) => (
                                <tr key={c.id} className="border-t">
                                    <td className="p-3 font-medium">
                                        {view === 'trash' ? (
                                            c.name
                                        ) : (
                                            <Link className="underline" href={base + '/' + c.id}>
                                                {c.name}
                                            </Link>
                                        )}
                                        <p className="text-muted-foreground mt-1 text-xs">{modeLabel(c.delivery_mode)}</p>
                                    </td>
                                    <td>{c.subject}</td>
                                    <td>
                                        <DeliveryStatus status={c.status} />
                                    </td>
                                    <td>{c.scheduled_at ? new Date(c.scheduled_at).toLocaleString() : 'Not scheduled'}</td>
                                    {canManage && (
                                        <td className="p-2">
                                            <div className="flex flex-wrap gap-2">
                                                {view === 'active' && !['scheduled', 'processing', 'paused'].includes(c.status) && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => {
                                                            if (
                                                                confirm(
                                                                    'Archive this campaign? Its delivery history and reports will remain available.',
                                                                )
                                                            )
                                                                router.post(`${base}/${c.id}/archive`);
                                                        }}
                                                    >
                                                        Archive
                                                    </Button>
                                                )}
                                                {view !== 'trash' && c.status === 'draft' && !c.snapshot && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => {
                                                            if (confirm('Move this unsent draft to Recently deleted?'))
                                                                router.delete(`${base}/${c.id}`);
                                                        }}
                                                    >
                                                        Delete
                                                    </Button>
                                                )}
                                                {view !== 'active' && (
                                                    <Button size="sm" variant="outline" onClick={() => router.post(`${base}/${c.id}/restore`)}>
                                                        Restore
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {!campaigns.data.length && (
                        <div className="text-muted-foreground py-10 text-center text-sm">
                            <Mail aria-hidden="true" className="mx-auto mb-3 size-6" />
                            <p className="text-foreground font-medium">No campaigns yet</p>
                            <p className="mt-1">Your campaigns will appear here.</p>
                        </div>
                    )}
                </div>
                <Pages page={campaigns} />
            </Panel>
        </Workspace>
    );
}
