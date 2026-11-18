import { Link, useForm, usePage } from '@inertiajs/react';
import TebLayout from '../../../Layouts/TebLayout';
import Field from '../../../Components/Teb/Field';
import MultiSelect from '../../../Components/Teb/MultiSelect';
import { useTebUrl } from '../../../Components/Teb/urls';

// Slide 2 "Register Project Entry" — also used for editing while the entry is still editable.
export default function Form({ p, customers, departments, employees = [], ownerName, today }) {
    const tebUrl = useTebUrl();
    const { classifications, typesOfChange, charterFields, charterRequired, bmpDrivers, bmpCategories, bmpInactive = {} } = usePage().props.teb.options;
    const editing = !!p;
    const hasFmea = editing && p.attachments.some((a) => a.type === 'fmea');

    const { data, setData, post, processing, errors } = useForm({
        title: p?.title ?? '',
        customer_ids: p?.customer_ids ?? [],
        types_of_change: p?.types_of_change ?? [],
        affected_areas: p?.affected_area_ids ?? [],
        bmp: { ...(p?.charter?.bmp ?? {}) },
        charter: { ...Object.fromEntries(Object.keys(charterFields).map((k) => [k, ''])), team_leader: ownerName ?? '', member_ids: [], sponsor_ids: [], timeline_start: '', timeline_end: '', ...(p?.charter ?? {}) },
        charter_file: null,
        fmea: null,
        attachments: [],
        ...(editing ? { _method: 'put' } : {}),
    });

    // Charter fields picked from the masterlist employee list (several allowed); the server stores the names too.
    const people = { members: 'member_ids', sponsor_name: 'sponsor_ids' };
    const short = ['team_leader', 'timeline', 'members', 'sponsor_name'];
    const setCharter = (k, v) => setData('charter', { ...data.charter, [k]: v });

    const toggle = (key, value) => setData(key, data[key].includes(value) ? data[key].filter((v) => v !== value) : [...data[key], value]);

    // Classification follows the BMP categories picked: Major when any of them is Major (the server does the same).
    const picked = Object.entries(data.bmp).filter(([, c]) => c !== undefined);
    const classes = picked.map(([d, c]) => bmpCategories[d]?.[c]).filter(Boolean);
    const classification = classes.length ? (classes.includes('major') ? 'major' : 'minor') : '';
    const setBmp = (d, category) => {
        const next = { ...data.bmp };
        if (category === undefined) delete next[d]; else next[d] = category;
        setData('bmp', next);
    };

    const submit = (e) => {
        e.preventDefault();
        post(editing ? tebUrl.update(p.id) : tebUrl.projects(), { forceFormData: true });
    };

    return (
        <TebLayout title={editing ? 'Edit Project Entry' : 'Register Project Entry'}>
            <h1>{editing ? 'Edit Project Entry' : 'Register Project Entry'}</h1>
            <form onSubmit={submit}>
                <div className="teb-card teb-grid">
                    <div className="teb-grid teb-g2">
                        <Field label="TRB Change Title" htmlFor="title" error={errors.title}>
                            <input type="text" id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                        </Field>
                        <Field label="TRB No">
                            <input type="text" value={p?.teb_no || 'Auto-generated after review (e.g. 2026-00001)'} disabled />
                        </Field>
                    </div>
                    <div className="teb-grid teb-g2">
                        <Field label="Customer(s)" htmlFor="customer_ids" error={errors.customer_ids ?? Object.entries(errors).find(([k]) => k.startsWith('customer_ids.'))?.[1] ?? (customers.length === 0 ? 'Customer list (qdn_db.customer_list) could not be loaded.' : null)}>
                            <MultiSelect id="customer_ids" options={customers} value={data.customer_ids} showAll={false}
                                onChange={(ids) => setData('customer_ids', ids)} placeholder="Select customer(s)…" />
                        </Field>
                        <Field label="Date Created"><input type="text" value={p?.created_at ?? today} disabled /></Field>
                    </div>
                    <Field label="BMP Alignment / Driver (TQRDC)" error={errors.bmp}>
                        <div className="teb-bmp">
                            {Object.entries(bmpDrivers).map(([d, label]) => {
                                const on = data.bmp[d] !== undefined;
                                return (
                                    <div key={d} className="teb-bmp-row">
                                        <label><input type="checkbox" checked={on} onChange={() => setBmp(d, on ? undefined : '')} /> {d} – {label}</label>
                                        <select value={data.bmp[d] ?? ''} disabled={!on} onChange={(e) => setBmp(d, e.target.value)} aria-label={`${label} category`}>
                                            <option value="">{on ? 'Select category…' : '—'}</option>
                                            {Object.entries(bmpCategories[d] ?? {}).filter(([c]) => !(bmpInactive[d] ?? []).includes(c) || data.bmp[d] === c).map(([c, cls]) => <option key={c} value={c}>{c} ({classifications[cls]})</option>)}
                                        </select>
                                        {errors[`bmp.${d}`] && <div className="teb-err">{errors[`bmp.${d}`]}</div>}
                                    </div>
                                );
                            })}
                        </div>
                    </Field>
                    <div className="teb-grid teb-g2">
                        <Field label="Change Classification (from BMP categories)" error={errors.classification ?? errors.fmea}>
                            <div>
                                {classification
                                    ? <span className={`teb-chip teb-badge ${classification === 'major' ? 'a-rejected' : 'a-approved'}`} style={{ fontSize: 14, padding: '6px 14px' }}>{classifications[classification]}</span>
                                    : <span className="teb-small teb-muted">Check a BMP driver and pick its category.</span>}
                            </div>
                            {classification === 'major' && (
                                <div style={{ marginTop: 8 }}>
                                    <label className="teb-l" htmlFor="fmea">FMEA document (required for Major)</label>
                                    <input type="file" id="fmea" onChange={(e) => setData('fmea', e.target.files[0])} />
                                    {hasFmea && (
                                        <div className="teb-small teb-muted">On file: {p.attachments.filter((a) => a.type === 'fmea').map((a) => a.original_name).join(', ')}</div>
                                    )}
                                </div>
                            )}
                        </Field>
                        <Field label="Type of Change" error={errors.types_of_change}>
                            <div className="teb-checks">
                                {Object.entries(typesOfChange).map(([k, v]) => (
                                    <label key={k}><input type="checkbox" checked={data.types_of_change.includes(k)} onChange={() => toggle('types_of_change', k)} /> {v}</label>
                                ))}
                            </div>
                        </Field>
                    </div>
                    <Field label="Affected Areas (department)" htmlFor="affected_areas" error={errors.affected_areas}>
                        <MultiSelect id="affected_areas" options={departments} value={data.affected_areas}
                            onChange={(ids) => setData('affected_areas', ids)} placeholder="Select department(s)…" allLabel="All departments" />
                    </Field>
                </div>
                <div className="teb-card teb-grid">
                    <h2 style={{ margin: 0 }}>Step 1 · Project Charter</h2>
                    <p className="teb-small teb-muted" style={{ margin: 0 }}>Fill in the charter here; you may also attach the charter document (TCMB template).</p>
                    {/* The statements are full-width text areas; the people and the timeline sit two to a row below. */}
                    {Object.entries(charterFields).filter(([k]) => !short.includes(k)).map(([k, label]) => (
                        <Field key={k} label={label + (charterRequired.includes(k) ? ' *' : '')} htmlFor={`charter_${k}`} error={errors[`charter.${k}`]}>
                            <textarea id={`charter_${k}`} rows={4} value={data.charter[k] ?? ''} onChange={(e) => setCharter(k, e.target.value)} />
                        </Field>
                    ))}
                    <div className="teb-grid teb-g2">
                        {short.map((k) => (
                            <Field key={k} label={charterFields[k] + (charterRequired.includes(k) ? ' *' : '')} htmlFor={`charter_${k}`}
                                error={errors[`charter.${k}`] ?? (k === 'timeline' ? errors['charter.timeline_start'] ?? errors['charter.timeline_end'] : null)}>
                                {people[k]
                                    ? <MultiSelect id={`charter_${k}`} options={employees} value={data.charter[people[k]] ?? []} showAll={false}
                                        onChange={(ids) => setCharter(people[k], ids)}
                                        placeholder={k === 'members' ? 'Select team members…' : 'Select sponsor(s)…'} />
                                    : k === 'timeline'
                                    ? (
                                        <div className="teb-grid teb-g2" style={{ gap: 8 }}>
                                            <label className="teb-small">Start
                                                <input type="date" id="charter_timeline" value={data.charter.timeline_start ?? ''} onChange={(e) => setCharter('timeline_start', e.target.value)} />
                                            </label>
                                            <label className="teb-small">Target completion
                                                <input type="date" value={data.charter.timeline_end ?? ''} min={data.charter.timeline_start || undefined} onChange={(e) => setCharter('timeline_end', e.target.value)} />
                                            </label>
                                            {!data.charter.timeline_start && !data.charter.timeline_end && data.charter.timeline && (
                                                <div className="teb-small teb-muted" style={{ gridColumn: '1 / -1' }}>Entered before: {data.charter.timeline}</div>
                                            )}
                                        </div>
                                    )
                                    : <input type="text" id={`charter_${k}`} value={data.charter[k] ?? ''} onChange={(e) => setCharter(k, e.target.value)} />}
                            </Field>
                        ))}
                    </div>
                    <Field label="Project Charter document (optional)" htmlFor="charter_file" error={errors.charter_file}>
                        <input type="file" id="charter_file" onChange={(e) => setData('charter_file', e.target.files[0])} />
                        {editing && p.attachments.some((a) => a.type === 'charter') && (
                            <div className="teb-small teb-muted">On file: {p.attachments.filter((a) => a.type === 'charter').map((a) => a.original_name).join(', ')}</div>
                        )}
                    </Field>
                    <Field label="Other supporting documents" htmlFor="attachments">
                        <input type="file" id="attachments" multiple onChange={(e) => setData('attachments', Array.from(e.target.files))} />
                    </Field>
                </div>
                <div className="teb-row">
                    <button className="teb-btn" disabled={processing}>{editing ? 'Save changes' : 'Submit for review'}</button>
                    <Link className="teb-btn ghost" href={editing ? tebUrl.show(p.id) : tebUrl.dashboard()}>Back</Link>
                </div>
            </form>
        </TebLayout>
    );
}
