import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

// Search / category / status (and optional date range) filter bar for any TEB list.
export default function Filters({ action, filters = {}, statuses, keep = {}, dates = false }) {
    const { categories, statuses: labels } = usePage().props.teb.options;
    const [f, setF] = useState({ q: '', category: '', status: '', from: '', to: '', ...filters });
    const set = (k) => (e) => setF({ ...f, [k]: e.target.value });

    const submit = (e) => {
        e.preventDefault();
        const data = { ...keep };
        Object.entries(f).forEach(([k, v]) => { if (v) data[k] = v; });
        router.get(action, data, { preserveState: true });
    };

    return (
        <form onSubmit={submit} className="teb-row teb-noprint" style={{ marginBottom: 16 }}>
            <input type="text" value={f.q} onChange={set('q')} placeholder="Search TEB No, title, customer, owner" style={{ maxWidth: 280 }} />
            <select value={f.category} onChange={set('category')} style={{ maxWidth: 280 }}>
                <option value="">All categories</option>
                {Object.entries(categories).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select>
            <select value={f.status} onChange={set('status')} style={{ maxWidth: 220 }}>
                <option value="">All statuses</option>
                {(statuses ?? Object.keys(labels)).map((s) => <option key={s} value={s}>{labels[s]}</option>)}
            </select>
            {dates && (
                <>
                    <input type="date" value={f.from} onChange={set('from')} title="Created from" style={{ maxWidth: 160 }} />
                    <input type="date" value={f.to} onChange={set('to')} title="Created to" style={{ maxWidth: 160 }} />
                </>
            )}
            <button className="teb-btn ghost">Filter</button>
        </form>
    );
}
