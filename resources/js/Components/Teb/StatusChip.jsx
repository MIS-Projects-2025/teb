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
};

export default function StatusChip({ status, label }) {
    const labels = usePage().props.teb?.options?.statuses ?? {};
    return <span className={`teb-chip teb-badge s-${status}`}>{label ?? labels[status] ?? status}</span>;
}
