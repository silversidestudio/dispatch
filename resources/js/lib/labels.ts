export function readableLabel(value: string) {
    const words = value.replace(/[_.]+/g, ' ').trim();
    return words ? words[0].toUpperCase() + words.slice(1) : '—';
}

export function eligibilityLabel(value: string) {
    const labels: Record<string, string> = {
        csv_import: 'Eligible · CSV import',
        consent: 'Consent recorded',
        documented_basis: 'Documented eligibility',
        unknown: 'Not yet confirmed',
        unsubscribed: 'Unsubscribed',
        hard_bounce: 'Hard bounce',
        complaint: 'Spam complaint',
        administratively_blocked: 'Blocked by an admin',
        suppressed: 'Suppressed',
    };

    return Object.hasOwn(labels, value) ? labels[value] : readableLabel(value);
}
