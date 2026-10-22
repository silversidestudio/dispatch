import { Button } from '@/components/ui/button';
import { Panel, Workspace } from '@/components/workspace';
import { Link } from '@inertiajs/react';
import { Activity, ArrowUpRight, Check, Mail, ShieldCheck, Users } from 'lucide-react';
type Props = {
    organisation: { id: number; name: string; role: string; members: number };
    canManage: boolean;
    activity: { id: number; action: string; created_at: string }[];
};
export default function Dashboard({ organisation, canManage, activity }: Props) {
    return (
        <Workspace
            title="Workspace overview"
            subtitle={`Welcome to ${organisation.name}. Build your next chapter, one connection at a time.`}
            actions={
                <Button variant="outline" asChild>
                    <Link href={`/organisations/${organisation.id}`}>
                        Workspace settings <ArrowUpRight className="size-4" />
                    </Link>
                </Button>
            }
        >
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-indigo-500/20 bg-indigo-500/5 px-5 py-4">
                <div className="flex items-center gap-3">
                    <ShieldCheck className="size-5 text-indigo-500" />
                    <div>
                        <p className="text-sm font-medium">Development mode · Sending is disabled</p>
                        <p className="text-muted-foreground mt-1 text-xs">
                            A secure foundation for your email operations. No messages leave this application.
                        </p>
                    </div>
                </div>
                <span className="rounded-full border px-3 py-1 text-xs">Phase 1A</span>
            </div>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {[
                    { label: 'Workspace members', value: organisation.members, detail: 'From your organisation', icon: Users },
                    { label: 'Your permission', value: organisation.role.replace('_', ' '), detail: 'Enforced on the server', icon: ShieldCheck },
                    { label: 'Campaign activity', value: '—', detail: 'Available in Phase 1C', icon: Mail },
                    { label: 'Accepted messages', value: '—', detail: 'Delivery has not started', icon: Activity },
                ].map((c) => (
                    <section key={c.label} className="bg-card rounded-2xl border p-5 shadow-xs">
                        <div className="text-muted-foreground flex items-center justify-between text-sm">
                            {c.label}
                            <c.icon className="size-4" />
                        </div>
                        <div className="mt-5 text-2xl font-semibold tracking-tight capitalize">{c.value}</div>
                        <p className="text-muted-foreground mt-2 text-xs">{c.detail}</p>
                    </section>
                ))}
            </div>
            <div className="grid gap-6 xl:grid-cols-[1.7fr_1fr]">
                <Panel title="Campaign activity" description="Your future campaign performance, with precise delivery definitions.">
                    <div className="bg-muted/20 flex h-64 flex-col items-center justify-center rounded-xl border border-dashed text-center">
                        <div className="bg-background mb-4 rounded-xl border p-3">
                            <Activity className="text-muted-foreground size-6" />
                        </div>
                        <h3 className="text-sm font-medium">A clean slate for your first campaign</h3>
                        <p className="text-muted-foreground mt-2 max-w-xs text-sm">
                            Charts will appear when campaign and simulated delivery data are available.
                        </p>
                        <span className="text-muted-foreground mt-4 text-xs">No activity recorded · No demonstration metrics</span>
                    </div>
                </Panel>
                <Panel title="Foundation checklist" description="The building blocks of your workspace.">
                    <ul className="space-y-5">
                        {[
                            'Secure account and verification',
                            'Isolated organisation workspace',
                            'Role-based access controls',
                            'External delivery disabled',
                        ].map((x) => (
                            <li key={x} className="flex items-center gap-3 text-sm">
                                <span className="rounded-full bg-emerald-500/10 p-1 text-emerald-600 dark:text-emerald-400">
                                    <Check className="size-3.5" />
                                </span>
                                {x}
                            </li>
                        ))}
                    </ul>
                    <div className="mt-6 border-t pt-5">
                        <p className="text-muted-foreground text-xs leading-5">
                            Next milestone: contacts, marketing eligibility and suppression management.
                        </p>
                    </div>
                </Panel>
            </div>
            <div className="grid gap-6 lg:grid-cols-2">
                <Panel title="Recent workspace activity" description="Recorded actions for this organisation only.">
                    {activity.length ? (
                        <ul className="divide-y">
                            {activity.map((a) => (
                                <li key={a.id} className="flex justify-between gap-4 py-3 text-sm">
                                    <span>{a.action.replaceAll('.', ' ').replaceAll('_', ' ')}</span>
                                    <time className="text-muted-foreground text-xs">{new Date(a.created_at).toLocaleDateString()}</time>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-muted-foreground py-8 text-center text-sm">No workspace activity yet.</p>
                    )}
                </Panel>
                <Panel title="Sending infrastructure" description="Clear status, without implied delivery readiness.">
                    <dl className="space-y-4 text-sm">
                        {[
                            ['External provider', 'Disabled'],
                            ['Sending domains', 'Not configured'],
                            ['Delivery events', 'Available in Phase 1D'],
                            ['Workspace access', canManage ? 'Administrator access' : 'Restricted by your role'],
                        ].map(([k, v]) => (
                            <div key={k} className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">{k}</dt>
                                <dd>{v}</dd>
                            </div>
                        ))}
                    </dl>
                </Panel>
            </div>
        </Workspace>
    );
}
