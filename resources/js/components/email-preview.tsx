import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { AlignLeft, Monitor, Smartphone } from 'lucide-react';
import { useState } from 'react';
export function EmailPreview({
    src,
    title,
    subject,
    previewText,
    senderName,
    senderEmail = 'preview@example.test',
    text,
    version,
    postalAddress,
    unsaved = false,
}: {
    src: string;
    title: string;
    subject: string;
    previewText?: string | null;
    senderName: string;
    senderEmail?: string;
    text: string;
    version?: string;
    postalAddress?: string | null;
    unsaved?: boolean;
}) {
    const [mode, setMode] = useState<'desktop' | 'mobile' | 'text'>('desktop');
    const sample = (value: string) =>
        value
            .replaceAll('{{first_name}}', 'Alex')
            .replaceAll('{{last_name}}', 'Example')
            .replaceAll('{{email}}', 'preview@example.test')
            .replaceAll('{{sender_name}}', senderName)
            .replaceAll('{{sender_address}}', postalAddress || '10 Example Street, London · demonstration address')
            .replaceAll('{{unsubscribe_url}}', '[Demonstration unsubscribe link]');
    return (
        <section id="email-preview" className="min-w-0 overflow-hidden rounded-xl border" aria-label="Email preview modes">
            <div className="bg-background flex flex-wrap items-center justify-between gap-3 border-b p-3 sm:px-5">
                <div>
                    <p className="text-sm font-medium">Preview your email</p>
                    <p className="text-muted-foreground mt-1 text-xs">Saved content · sample personalisation · HTTPS images may load</p>
                </div>
                <TooltipProvider>
                    <div className="bg-muted/50 flex rounded-lg border p-1" role="group" aria-label="Preview viewport">
                        {(
                            [
                                { mode: 'desktop', name: 'Desktop preview', Icon: Monitor },
                                { mode: 'mobile', name: 'Mobile preview', Icon: Smartphone },
                                { mode: 'text', name: 'Plain-text preview', Icon: AlignLeft },
                            ] as const
                        ).map(({ mode: m, name, Icon }) => (
                            <Tooltip key={m}>
                                <TooltipTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={name}
                                        aria-pressed={mode === m}
                                        onClick={() => setMode(m)}
                                        className={cn('size-10 rounded-md', mode === m && 'bg-background shadow-sm')}
                                    >
                                        <Icon className="size-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>{name}</TooltipContent>
                            </Tooltip>
                        ))}
                    </div>
                </TooltipProvider>
            </div>
            {unsaved && (
                <p role="status" className="border-b bg-amber-50 px-4 py-3 text-xs text-amber-900">
                    Save your changes to update this preview.
                </p>
            )}
            <div className="bg-slate-100 p-2 sm:p-6 dark:bg-slate-900/50">
                <div
                    className={cn(
                        'mx-auto max-w-full min-w-0 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm',
                        mode === 'mobile' ? 'w-[375px]' : 'w-[680px]',
                    )}
                    data-preview-mode={mode}
                >
                    <div className="border-b border-slate-200 bg-white p-4 text-slate-700 sm:p-5">
                        <p className="text-xs text-slate-500">
                            From <span className="font-medium text-slate-800">{senderName}</span>
                        </p>
                        <p className="mt-1 text-xs break-all text-slate-500">{senderEmail} · to Alex Example (sample)</p>
                        <p className="mt-4 text-base font-semibold break-words text-slate-900">{subject || 'Your email subject'}</p>
                        {previewText && <p className="mt-1 text-xs break-words text-slate-500">{previewText}</p>}
                    </div>
                    {mode === 'text' ? (
                        <pre
                            aria-label="Plain-text email preview"
                            className="min-h-96 p-5 font-mono text-sm leading-7 break-words whitespace-pre-wrap text-slate-700"
                        >
                            {sample(text)}
                        </pre>
                    ) : (
                        <iframe
                            key={`${src}-${version}-${mode}`}
                            title={title}
                            sandbox=""
                            referrerPolicy="no-referrer"
                            src={src}
                            className="block h-[900px] w-full border-0 bg-white"
                        />
                    )}
                </div>
                <p className="mt-4 text-center text-xs text-slate-500 dark:text-slate-400">
                    {mode === 'mobile'
                        ? '375 px mobile viewport · narrows to fit your screen'
                        : mode === 'text'
                          ? 'Plain text · uses the saved alternative'
                          : '600 px email content · responsive HTML'}
                </p>
            </div>
        </section>
    );
}
