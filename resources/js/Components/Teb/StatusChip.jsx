import { usePage } from '@inertiajs/react';

// Main colour per status; the badge background/text colours live in teb.css (.s-<status>).
export const STATUS_COLORS = {
    submitted_for_review: '#2a4db3',
    open: '#0b5c9e',
    hold: '#a86400',
    ongoing: '#5b3cc4',
    pending_teb_number: '#b08800',
    reviewed: '#1f7a4d',
    for_approval: '#a3236a',
    returned: '#b42318',
    approved: '#11603a',
    revise: '#b42318',
    dropped: '#475467',
    plan_for_approval: '#a3236a',
    plan_returned: '#b42318',
    trial_authorized: '#0b5c9e',
    adi_review: '#5b3cc4',
    customer_hold: '#a86400',
    doc_release: '#2a4db3',
    readiness_gaps: '#a86400',
    implementation: '#1f7a4d',
    effectiveness: '#b08800',
    containment: '#b42318',
    closed: '#11603a',
};

export default function StatusChip({ status, label }) {
    const labels = usePage().props.teb?.options?.statuses ?? {};
    return <span className={`teb-chip teb-badge s-${status}`}>{label ?? labels[status] ?? status}</span>;
}
