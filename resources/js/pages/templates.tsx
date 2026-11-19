import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmailPreview } from '@/components/email-preview';
import { Company } from '@/components/marketing';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Workspace } from '@/components/workspace';
import { Link, router } from '@inertiajs/react';
import { ArrowUpRight, Copy, Eye, FilePlus2, Pencil, Sparkles, Trash2 } from 'lucide-react';
import { useState } from 'react';
export type Template = { id: number; name: string; category: string; subject: string; preview_text: string | null; html: string; text_body: string };
type Starter = { name: string; category: string; subject: string; description: string; html: string; text_body: string };
function Thumbnail({ src, name }: { src: string; name: string }) {
    return (
        <div className="relative h-52 overflow-hidden border-b bg-slate-100" aria-hidden="true">
            <iframe
                title={`${name} thumbnail`}
                src={src}
                sandbox=""
                referrerPolicy="no-referrer"
                tabIndex={-1}
                loading="lazy"
                className="pointer-events-none absolute top-0 left-1/2 h-[760px] w-[600px] origin-top -translate-x-1/2 scale-[0.48] border-0 bg-white"
            />
            <div className="pointer-events-none absolute inset-x-0 bottom-0 h-8 bg-linear-to-t from-slate-100/80 to-transparent" />
        </div>
    );
}
export default function Templates({
    company,
    templates,
    starters,
    customGallery,
    canManage,
}: {
    company: Company;
    templates: Template[];
    starters: Starter[];
    customGallery: boolean;
    canManage: boolean;
}) {
    const base = `/organisations/${company.id}/templates`;
    const [preview, setPreview] = useState<{ src: string; name: string; subject: string; text: string; previewText: string | null } | null>(null);
    const [filter, setFilter] = useState('All designs');
    const categories = ['All designs', ...new Set(starters.map((s) => s.category))];
    return (
        <Workspace
            title="Email templates"
            subtitle="Saved emails and designs for future campaigns."
            actions={
                canManage && (
                    <Button onClick={() => router.post(base + '/basic')}>
                        <FilePlus2 className="mr-2 size-4" />
                        Create basic email
                    </Button>
                )
            }
        >
            {canManage && (
                <div className="bg-card flex flex-wrap items-center justify-between gap-4 rounded-xl border p-5">
                    <div>
                        <h2 className="font-semibold">Basic email</h2>
                        <p className="text-muted-foreground mt-1 max-w-xl text-sm">
                            A simple text-led email with controls for fonts, size, colour and links. You can also reuse your company signature.
                        </p>
                    </div>
                    <Button variant="outline" onClick={() => router.post(base + '/basic')}>
                        Use basic email
                    </Button>
                </div>
            )}
            <section className="min-w-0">
                <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 className="text-lg font-semibold">Your library</h2>
                        <p className="text-muted-foreground mt-1 text-sm">Your saved email templates.</p>
                    </div>
                    <span className="text-muted-foreground text-sm">
                        {templates.length} {templates.length === 1 ? 'template' : 'templates'}
                    </span>
                </div>
                <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    {templates.map((t) => (
                        <article key={t.id} className="bg-card min-w-0 overflow-hidden rounded-xl border shadow-xs">
                            <Thumbnail src={`${base}/${t.id}/preview`} name={t.name} />
                            <div className="space-y-4 p-5">
                                <div>
                                    <p className="text-muted-foreground text-[11px] font-medium tracking-widest uppercase">{t.category}</p>
                                    <h3 className="mt-2 font-semibold break-words">{t.name}</h3>
                                    <p className="text-muted-foreground mt-2 line-clamp-2 text-sm">{t.preview_text || t.subject}</p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setPreview({
                                                src: `${base}/${t.id}/preview`,
                                                name: t.name,
                                                subject: t.subject,
                                                text: t.text_body ?? '',
                                                previewText: t.preview_text,
                                            })
                                        }
                                    >
                                        <Eye className="mr-2 size-3.5" />
                                        Preview
                                    </Button>
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={`${base}/${t.id}`}>
                                            <Pencil className="mr-2 size-3.5" />
                                            {canManage ? 'Edit' : 'View'}
                                        </Link>
                                    </Button>
                                    {canManage && (
                                        <>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                aria-label={`Duplicate ${t.name}`}
                                                onClick={() => router.post(`${base}/${t.id}/duplicate`)}
                                            >
                                                <Copy className="size-3.5" />
                                            </Button>
                                            <ConfirmDialog
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        aria-label={`Delete ${t.name}`}
                                                        className="text-destructive hover:text-destructive"
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                }
                                                title={`Delete “${t.name}” from Your Library?`}
                                                description="Campaign content already using this template will remain unchanged."
                                                confirmLabel="Delete template"
                                                onConfirm={() => router.delete(`${base}/${t.id}`)}
                                            />
                                        </>
                                    )}
                                </div>
                            </div>
                        </article>
                    ))}
                </div>
                {!templates.length && (
                    <div className="bg-card rounded-xl border border-dashed px-6 py-12 text-center">
                        <FilePlus2 className="text-muted-foreground mx-auto mb-4 size-8" />
                        <h3 className="font-medium">Your next great email starts here</h3>
                        <p className="text-muted-foreground mx-auto mt-2 max-w-md text-sm">
                            No saved templates yet. Start with a basic email or choose one of the designs below.
                        </p>
                    </div>
                )}
            </section>
            <section>
                <div className="mb-5">
                    <div className="mb-2 flex items-center gap-2">
                        <Sparkles className="size-4 text-emerald-600" />
                        <p className="text-xs font-medium tracking-widest uppercase">
                            {customGallery ? 'Relaxa collection' : 'The Studio collection · v2'}
                        </p>
                    </div>
                    <h2 className="text-lg font-semibold">{customGallery ? 'Relaxa webinar designs' : 'Designed to make a good impression'}</h2>
                    <p className="text-muted-foreground mt-1 text-sm">
                        {customGallery
                            ? 'Choose a Relaxa design to add an editable copy to Your Library. Changes to that copy stay separate.'
                            : 'Five original, responsive designs. Existing templates are never replaced.'}
                    </p>
                </div>
                <div className="mb-6 flex flex-wrap gap-2" role="group" aria-label="Template categories">
                    {categories.map((c) => (
                        <Button
                            key={c}
                            type="button"
                            size="sm"
                            variant={filter === c ? 'secondary' : 'ghost'}
                            aria-pressed={filter === c}
                            onClick={() => setFilter(c)}
                        >
                            {c}
                        </Button>
                    ))}
                </div>
                <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    {starters.map((s, i) =>
                        filter === 'All designs' || filter === s.category ? (
                            <article
                                key={s.name}
                                className="bg-card min-w-0 overflow-hidden rounded-xl border shadow-xs transition-shadow hover:shadow-md"
                            >
                                <Thumbnail src={`/organisations/${company.id}/starter-previews/${i}`} name={s.name} />
                                <div className="space-y-4 p-5">
                                    <div>
                                        <p className="text-muted-foreground text-[11px] font-medium tracking-widest uppercase">{s.category}</p>
                                        <h3 className="mt-2 font-semibold">{s.name.replace(' · Studio v2', '')}</h3>
                                        <p className="text-muted-foreground mt-2 min-h-10 text-sm">{s.description}</p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {canManage && (
                                            <Button size="sm" onClick={() => router.post(base + '/starter', { starter: i })}>
                                                Use template
                                                <ArrowUpRight className="ml-2 size-3.5" />
                                            </Button>
                                        )}
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                setPreview({
                                                    src: `/organisations/${company.id}/starter-previews/${i}`,
                                                    name: s.name,
                                                    subject: s.subject,
                                                    text: s.text_body,
                                                    previewText: s.description,
                                                })
                                            }
                                        >
                                            <Eye className="mr-2 size-3.5" />
                                            Preview
                                        </Button>
                                    </div>
                                </div>
                            </article>
                        ) : null,
                    )}
                </div>
            </section>
            <Dialog
                open={!!preview}
                onOpenChange={(v) => {
                    if (!v) setPreview(null);
                }}
            >
                <DialogContent className="max-h-[94dvh] max-w-[calc(100%-1rem)] overflow-y-auto p-3 sm:max-w-4xl sm:p-6">
                    <DialogHeader>
                        <DialogTitle>{preview?.name}</DialogTitle>
                        <DialogDescription>Sample personalisation only. Email-client rendering still needs a Gmail/Outlook check.</DialogDescription>
                    </DialogHeader>
                    {preview && (
                        <EmailPreview
                            src={preview.src}
                            title="Template library preview"
                            subject={preview.subject}
                            previewText={preview.previewText}
                            senderName={company.name}
                            postalAddress={company.postal_address}
                            text={preview.text}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </Workspace>
    );
}
