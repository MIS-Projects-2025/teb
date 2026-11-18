import Field from './Field';

// Read-only project details laid out like the Register Project Entry form.
export default function FormDetails({ p, options }) {
    const { classifications, typesOfChange, charterFields, bmpDrivers, bmpCategories } = options;
    const types = p.types_of_change ?? [];
    const charter = p.charter ?? {};
    const hasCharter = Object.keys(charterFields).some((k) => charter[k]);

    return (
        <div className="teb-grid teb-ro">
            <div className="teb-grid teb-g2">
                <Field label="TRB Change Title"><Value>{p.title}</Value></Field>
                <Field label="TRB No"><Value muted={!p.teb_no}>{p.teb_no || 'Not assigned yet'}</Value></Field>
            </div>
            <div className="teb-grid teb-g3">
                {p.category && <Field label="Category"><Value>{p.category_label}</Value></Field>}
                <Field label="Customer(s)"><Value>{p.customer_name}</Value></Field>
                <Field label="Date Created"><Value>{p.created_at}</Value></Field>
            </div>
            {Object.keys(charter.bmp ?? {}).length > 0 && (
                <Field label="BMP Alignment / Driver (TQRDC)">
                    <div className="teb-ro-box" style={{ display: 'grid', gap: 2 }}>
                        {Object.entries(charter.bmp).map(([d, c]) => (
                            <div key={d}><strong>{d} – {bmpDrivers[d]}:</strong> {c} ({classifications[bmpCategories[d]?.[c]] ?? '—'})</div>
                        ))}
                    </div>
                </Field>
            )}
            <div className="teb-grid teb-g2">
                <Field label="Change Classification">
                    <div className="teb-checks">
                        {Object.entries(classifications).map(([k, v]) => (
                            <label key={k}><input type="radio" checked={p.classification === k} disabled readOnly /> {v}</label>
                        ))}
                    </div>
                </Field>
                <Field label="Type of Change">
                    <div className="teb-checks">
                        {Object.entries(typesOfChange).map(([k, v]) => (
                            <label key={k}><input type="checkbox" checked={types.includes(k)} disabled readOnly /> {v}</label>
                        ))}
                    </div>
                </Field>
            </div>
            <Field label="Affected Areas (department)">
                <div className="teb-ro-box">
                    {p.affected_areas?.length
                        ? p.affected_areas.map((d) => <span key={d} className="teb-chip">{d}</span>)
                        : <span className="teb-muted">—</span>}
                </div>
            </Field>
            {/* Entries registered before the Project Charter keep their description fields. */}
            {!hasCharter && (
                <>
                    <div className="teb-grid teb-g2">
                        <Field label="Project Owner"><Value>{p.owner_name}</Value></Field>
                        <Field label="Department"><Value>{p.owner_department}</Value></Field>
                    </div>
                    <Field label="Project Description"><Value multi>{p.project_description}</Value></Field>
                    <div className="teb-grid teb-g2">
                        <Field label="Current Status"><Value multi>{p.current_status}</Value></Field>
                        <Field label="Change Description"><Value multi>{p.change_description}</Value></Field>
                    </div>
                </>
            )}
            {hasCharter && (
                <>
                    <h3 style={{ margin: '8px 0 0' }}>Project Charter</h3>
                    <div className="teb-grid teb-g2">
                        {Object.entries(charterFields).filter(([k]) => charter[k]).map(([k, label]) => (
                            <Field key={k} label={label}><Value multi={!['team_leader', 'sponsor_name', 'timeline'].includes(k)}>{charter[k]}</Value></Field>
                        ))}
                    </div>
                </>
            )}
            {p.completed_at && (
                <div className="teb-grid teb-g2">
                    <Field label="Completed"><Value>{p.completed_at + (p.completion_remarks ? ` · ${p.completion_remarks}` : '')}</Value></Field>
                    <Field label="4MCN Required"><Value>{(p.requires_4mcn ? 'Yes' : 'No') + (p.four_mcn ? ` · ${p.four_mcn.mcn_no}` : '')}</Value></Field>
                </div>
            )}
        </div>
    );
}

function Value({ children, multi, muted }) {
    const empty = children === null || children === undefined || children === '';
    return (
        <div className={`teb-ro-box${multi ? ' multi' : ''}${muted || empty ? ' teb-muted' : ''}`}>
            {empty ? '—' : children}
        </div>
    );
}
