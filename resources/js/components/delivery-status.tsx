import { Badge } from '@/components/ui/badge';
import { eligibilityLabel, readableLabel } from '@/lib/labels';
import { cn } from '@/lib/utils';

const tones = {
    green: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
    amber: 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200',
    red: 'border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200',
    neutral: 'bg-muted text-muted-foreground',
};
type Tone = keyof typeof tones;

const deliveryLabels: Record<string, string> = {
    accepted: 'Accepted by provider',
    sandbox_validated: 'Sandbox validated',
    uncertain: 'Needs review',
    spamreport: 'Spam complaint',
    bounce: 'Bounced',
    not_submitted: 'Not submitted',
    confirmed_not_submitted: 'Confirmed not submitted',
    group_unsubscribe: 'Unsubscribed',
};

export function StatusPill({ label, tone = 'neutral' }: { label: string; tone?: Tone }) {
    return (
        <Badge variant="outline" className={cn('gap-1.5 px-2.5 py-1 leading-tight', tones[tone])}>
            <span aria-hidden="true" className="size-1.5 shrink-0 rounded-full bg-current" />
            {label}
        </Badge>
    );
}

export function DeliveryStatus({ status, label }: { status?: string | null; label?: string }) {
    const value = status ?? '';
    const green = ['active', 'verified', 'completed', 'delivered', 'accepted', 'sandbox_validated', 'imported'];
    const amber = [
        'pending',
        'queued',
        'scheduled',
        'deferred',
        'processing',
        'submitting',
        'paused',
        'uncertain',
        'unverified',
        'preview',
        'processed',
    ];
    const red = ['failed', 'bounce', 'blocked', 'dropped', 'spamreport', 'rejected', 'suspended', 'unsubscribed'];
    const tone = green.includes(value) ? 'green' : amber.includes(value) ? 'amber' : red.includes(value) ? 'red' : 'neutral';
    return (
        <StatusPill
            label={label ?? (Object.hasOwn(deliveryLabels, value) ? deliveryLabels[value] : value ? readableLabel(value) : 'No feedback')}
            tone={tone}
        />
    );
}

export function EligibilityStatus({ status }: { status: string }) {
    const tone = ['csv_import', 'consent', 'documented_basis'].includes(status)
        ? 'green'
        : status === 'unknown'
          ? 'amber'
          : ['unsubscribed', 'suppressed', 'hard_bounce', 'complaint', 'administratively_blocked'].includes(status)
            ? 'red'
            : 'neutral';
    return <StatusPill label={eligibilityLabel(status)} tone={tone} />;
}
