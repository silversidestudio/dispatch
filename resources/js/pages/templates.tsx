import { Company } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Panel, Workspace } from '@/components/workspace';
import { Link, router } from '@inertiajs/react';
export type Template = { id: number; name: string; category: string; subject: string; preview_text: string | null; html: string; text_body: string };
export default function Templates({
    company,
    templates,
    starters,
    canManage,
}: {
    company: Company;
    templates: Template[];
    starters: { name: string; category: string; subject: string }[];
    canManage: boolean;
}) {
    const base = `/organisations/${company.id}/templates`;
    return (
        <Workspace title="Email templates" subtitle="Reusable, company-specific content. Original starter designs are included.">
            <Panel title="Your library">
                <div className="grid gap-4 md:grid-cols-3">
                    {templates.map((t) => (
                        <Link className="hover:bg-muted/20 rounded-xl border p-5" href={`${base}/${t.id}`} key={t.id}>
                            <p className="text-muted-foreground text-xs tracking-wide uppercase">{t.category}</p>
                            <h2 className="mt-3 font-semibold">{t.name}</h2>
                            <p className="text-muted-foreground mt-2 text-sm">{t.subject}</p>
                        </Link>
                    ))}
                </div>
                {!templates.length && <p className="text-muted-foreground py-8 text-center">Start with an original design below.</p>}
            </Panel>
            {canManage && (
                <Panel title="Original starter templates">
                    <div className="grid gap-4 md:grid-cols-3">
                        {starters.map((s, i) => (
                            <div className="rounded-xl border p-5" key={s.name}>
                                <p className="text-muted-foreground text-xs">{s.category}</p>
                                <h3 className="my-3 font-semibold">{s.name}</h3>
                                <Button variant="outline" onClick={() => router.post(base + '/starter', { starter: i })}>
                                    Use template
                                </Button>
                            </div>
                        ))}
                    </div>
                </Panel>
            )}
        </Workspace>
    );
}
