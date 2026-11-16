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

export function contactFieldLabel(value: string) {
    return (
        (
            {
                email: 'Email address',
                first_name: 'First name',
                last_name: 'Last name',
                company_name: 'Company name',
                telephone: 'Telephone',
                source: 'Contact source',
            } as Record<string, string>
        )[value] ?? readableLabel(value)
    );
}

export function modeLabel(value: string) {
    return (
        (
            {
                simulation: 'Local simulation',
                sendgrid_live: 'Live sending',
                sendgrid_sandbox: 'SendGrid sandbox',
            } as Record<string, string>
        )[value] ?? readableLabel(value)
    );
}
