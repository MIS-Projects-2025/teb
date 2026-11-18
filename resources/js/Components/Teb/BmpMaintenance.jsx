import { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import { useTebUrl } from './urls';

// BMP maintenance (Settings page): categories under each TQRDC driver and the classification each carries.
// Retired (inactive) categories are no longer offered on the register form, but entries that picked them keep them.
export default function BmpMaintenance({ rows = [] }) {
    const { bmpDrivers, classifications } = usePage().props.teb.options;
    const [driver, setDriver] = useState(Object.keys(bmpDrivers)[0]);
    const list = rows.filter((r) => r.driver === driver);

    return (
        <div className="teb-card" id="bmp">
            <h2>BMP Alignment categories</h2>
            <p className="teb-muted teb-small">
                The categories offered under each BMP driver on the register form. A project is Major when any category it picks is Major.
                A change of classification applies to entries registered or edited from now on. Renaming a category renames it on the entries
                that picked it. A category in use can't be deleted, so untick Active to stop offering it.
            </p>
            <div className="teb-row" style={{ gap: 6, flexWrap: 'wrap', marginBottom: 12 }}>
                {Object.entries(bmpDrivers).map(([d, label]) => (
                    <button key={d} type="button" className={`teb-btn sm${d === driver ? '' : ' ghost'}`} onClick={() => setDriver(d)}>
                        {d} – {label} ({rows.filter((r) => r.driver === d && r.active).length})
                    </button>
                ))}
            </div>
            <div className="teb-tbl">
                <table>
                    <thead><tr><th>Category</th><th style={{ width: 150 }}>Classification</th><th style={{ width: 70 }}>Active</th><th style={{ width: 80 }}>Used by</th><th style={{ width: 160 }} /></tr></thead>
                    <tbody>
                        {list.map((r) => <Row key={r.id} row={r} classifications={classifications} />)}
                        {list.length === 0 && <tr><td colSpan={5} className="teb-muted">No categories under this driver yet.</td></tr>}
                        <AddRow key={driver} driver={driver} classifications={classifications} />
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function Row({ row, classifications }) {
    const tebUrl = useTebUrl();
    const { data, setData, put, processing, errors, isDirty } = useForm({ name: row.name, classification: row.classification, active: row.active });
    const save = () => put(tebUrl.bmp(row.id), { preserveScroll: true });
    const remove = () => {
        if (confirm(`Delete “${row.name}”?`)) router.delete(tebUrl.bmp(row.id), { preserveScroll: true });
    };

    return (
        <tr style={row.active ? undefined : { opacity: 0.6 }}>
            <td>
                <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} aria-label="Category name" />
                {errors.name && <div className="teb-err">{errors.name}</div>}
            </td>
            <td>
                <select value={data.classification} onChange={(e) => setData('classification', e.target.value)} aria-label="Classification">
                    {Object.entries(classifications).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                </select>
            </td>
            <td style={{ textAlign: 'center' }}>
                <input type="checkbox" checked={data.active} onChange={(e) => setData('active', e.target.checked)} aria-label="Active" />
            </td>
            <td>{row.used ? `${row.used} ${row.used === 1 ? 'entry' : 'entries'}` : <span className="teb-muted">—</span>}</td>
            <td>
                <div className="teb-row" style={{ gap: 6 }}>
                    <button type="button" className="teb-btn sm" disabled={!isDirty || processing} onClick={save}>Save</button>
                    <button type="button" className="teb-btn sm bad" disabled={processing || row.used > 0} onClick={remove}
                        title={row.used ? 'In use: untick Active instead' : undefined}>Delete</button>
                </div>
            </td>
        </tr>
    );
}

function AddRow({ driver, classifications }) {
    const tebUrl = useTebUrl();
    const { data, setData, post, processing, errors, reset } = useForm({ driver, name: '', classification: 'minor' });
    const add = (e) => {
        e.preventDefault();
        post(tebUrl.bmp(), { preserveScroll: true, onSuccess: () => reset('name') });
    };

    return (
        <tr>
            <td>
                <input type="text" value={data.name} placeholder="New category…" onChange={(e) => setData('name', e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && add(e)} aria-label="New category name" />
                {errors.name && <div className="teb-err">{errors.name}</div>}
            </td>
            <td>
                <select value={data.classification} onChange={(e) => setData('classification', e.target.value)} aria-label="New category classification">
                    {Object.entries(classifications).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                </select>
            </td>
            <td colSpan={2} />
            <td><button type="button" className="teb-btn sm ok" disabled={processing || !data.name.trim()} onClick={add}>Add</button></td>
        </tr>
    );
}
