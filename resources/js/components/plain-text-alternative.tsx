import { Button } from '@/components/ui/button';
import { useState } from 'react';
export function PlainTextAlternative({
    companyId,
    html,
    value,
    onChange,
    editable = true,
}: {
    companyId: number;
    html: string;
    value: string;
    onChange: (v: string) => void;
    editable?: boolean;
}) {
    const [busy, setBusy] = useState(false),
        [error, setError] = useState('');
    async function update() {
        if (value.trim() && !confirm('Replace the plain-text alternative with text from the current email?')) return;
        setBusy(true);
        setError('');
        try {
            const cookie = document.cookie.split('; ').find((v) => v.startsWith('XSRF-TOKEN='));
            const response = await fetch(`/organisations/${companyId}/email-plain-text`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(cookie?.slice('XSRF-TOKEN='.length) ?? ''),
                },
                body: JSON.stringify({ html }),
            });
            if (!response.ok) throw new Error();
            onChange((await response.json()).text);
        } catch {
            setError('Could not update plain text. Refresh the page and try again.');
        } finally {
            setBusy(false);
        }
    }
    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <label htmlFor="plain-text-email" className="text-sm font-medium">
                    Plain-text alternative
                </label>
                {editable && (
                    <Button type="button" variant="outline" size="sm" disabled={busy} onClick={update}>
                        {busy ? 'Updating…' : 'Update from email'}
                    </Button>
                )}
            </div>
            <p className="text-muted-foreground text-xs">
                For recipients who read text-only emails. Update this after changing the message, then review before scheduling.
            </p>
            <textarea
                id="plain-text-email"
                aria-label="Plain-text alternative"
                readOnly={!editable}
                className="bg-background min-h-36 w-full rounded-lg border p-3 font-mono text-sm"
                value={value}
                onChange={(e) => onChange(e.target.value)}
            />
            {error && (
                <p role="alert" className="text-destructive text-sm">
                    {error}
                </p>
            )}
        </div>
    );
}
