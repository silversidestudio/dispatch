import { DeliveryStatus } from '@/components/delivery-status';
import { Company, Page, Pages } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Panel, Workspace } from '@/components/workspace';
import { modeLabel } from '@/lib/labels';
import { Link } from '@inertiajs/react';
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
    snapshot: Record<string, unknown> | null;
};
export default function Campaigns({ company, campaigns, canManage }: { company: Company; campaigns: Page<Campaign>; canManage: boolean }) {
    const base = `/organisations/${company.id}/campaigns`;
    return (
        <Workspace
            title="Campaigns"
            subtitle={`${company.name} · Prepare, preview and schedule with explicit provider controls.`}
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
                <div className="overflow-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr>
                                {['Campaign', 'Subject', 'Status', 'Scheduled'].map((s) => (
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
                                        <Link className="underline" href={base + '/' + c.id}>
                                            {c.name}
                                        </Link>
                                        <p className="text-muted-foreground mt-1 text-xs">{modeLabel(c.delivery_mode)}</p>
                                    </td>
                                    <td>{c.subject}</td>
                                    <td>
                                        <DeliveryStatus status={c.status} />
                                    </td>
                                    <td>{c.scheduled_at ? new Date(c.scheduled_at).toLocaleString() : 'Not scheduled'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {!campaigns.data.length && (
                        <div className="text-muted-foreground py-10 text-center text-sm">
                            <Mail aria-hidden="true" className="mx-auto mb-3 size-6" />
                            <p className="text-foreground font-medium">No campaigns yet</p>
                            <p className="mt-1">Add an audience and a sender, then create your first draft.</p>
                        </div>
                    )}
                </div>
                <Pages page={campaigns} />
            </Panel>
        </Workspace>
    );
}
