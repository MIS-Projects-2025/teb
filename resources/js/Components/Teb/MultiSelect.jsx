import { useEffect, useRef, useState } from 'react';

// Dropdown that allows several selections, with search and an "All" option.
// options: [{ id, name }], value: array of ids, onChange(newIds)
export default function MultiSelect({ id, options, value, onChange, placeholder = 'Select…', allLabel = 'All', showAll = true }) {
    const [open, setOpen] = useState(false);
    const [q, setQ] = useState('');
    const box = useRef(null);

    useEffect(() => {
        const close = (e) => { if (box.current && !box.current.contains(e.target)) setOpen(false); };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, []);

    const selected = options.filter((o) => value.includes(o.id));
    const all = options.length > 0 && selected.length === options.length;
    const shown = options.filter((o) => o.name.toLowerCase().includes(q.toLowerCase()));
    const toggle = (oid) => onChange(value.includes(oid) ? value.filter((v) => v !== oid) : [...value, oid]);

    return (
        <div className="teb-ms" ref={box}>
            <button type="button" id={id} className="teb-ms-btn" aria-haspopup="listbox" aria-expanded={open} onClick={() => setOpen(!open)}>
                <span className="teb-ms-chips">
                    {selected.length === 0 && <span className="teb-muted">{placeholder}</span>}
                    {all && showAll && <span className="teb-chip">{allLabel}</span>}
                    {!(all && showAll) && selected.map((o) => (
                        <span key={o.id} className="teb-chip">
                            {o.name}
                            <span role="button" aria-label={`Remove ${o.name}`} className="teb-ms-x"
                                onClick={(e) => { e.stopPropagation(); toggle(o.id); }}>×</span>
                        </span>
                    ))}
                </span>
                <span className="teb-ms-caret">▾</span>
            </button>
            {open && (
                <div className="teb-ms-panel" role="listbox" aria-multiselectable="true">
                    <input type="text" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search…" autoFocus />
                    {showAll && (
                        <label className="teb-ms-opt teb-ms-all">
                            <input type="checkbox" checked={all} onChange={() => onChange(all ? [] : options.map((o) => o.id))} /> {allLabel}
                        </label>
                    )}
                    <div className="teb-ms-list">
                        {shown.map((o) => (
                            <label key={o.id} className="teb-ms-opt">
                                <input type="checkbox" checked={value.includes(o.id)} onChange={() => toggle(o.id)} /> {o.name}
                            </label>
                        ))}
                        {shown.length === 0 && <div className="teb-muted teb-small" style={{ padding: 8 }}>No match.</div>}
                    </div>
                    <div className="teb-ms-foot">
                        <span className="teb-small teb-muted">{selected.length} selected</span>
                        <button type="button" className="teb-btn sm ghost" onClick={() => setOpen(false)}>Done</button>
                    </div>
                </div>
            )}
        </div>
    );
}
