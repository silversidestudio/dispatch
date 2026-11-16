import { Badge } from '@/components/ui/badge';
import { CircleCheck, CircleHelp, Clock3, Pause, TriangleAlert, XCircle } from 'lucide-react';

export function DeliveryStatus({ status }: { status?: string | null }) {
    const label =
        (
            {
                accepted: 'Accepted by provider',
                sandbox_validated: 'Sandbox validated',
                uncertain: 'Needs review',
                spamreport: 'Complaint',
            } as Record<string, string>
        )[status ?? ''] ??
        status?.replaceAll('_', ' ') ??
        'No feedback';
    const positive = ['completed', 'delivered', 'accepted', 'sandbox_validated'].includes(status ?? '');
    const failed = ['failed', 'bounce', 'blocked', 'dropped', 'spamreport', 'rejected'].includes(status ?? '');
    const Icon = positive
        ? CircleCheck
        : failed
          ? XCircle
          : status === 'uncertain'
            ? TriangleAlert
            : status === 'paused'
              ? Pause
              : ['pending', 'queued', 'scheduled', 'deferred', 'processing'].includes(status ?? '')
                ? Clock3
                : CircleHelp;
    return (
        <Badge
            variant={failed ? 'destructive' : 'secondary'}
            className={positive ? 'gap-1 bg-emerald-500/10 text-emerald-800 capitalize dark:text-emerald-300' : 'gap-1 capitalize'}
        >
            <Icon aria-hidden="true" className="size-3 shrink-0" />
            {label}
        </Badge>
    );
}
