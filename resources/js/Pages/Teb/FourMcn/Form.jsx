import { Link, useForm, usePage } from '@inertiajs/react';
import TebLayout from '../../../Layouts/TebLayout';
import Field from '../../../Components/Teb/Field';
import { useTebUrl } from '../../../Components/Teb/urls';

// "4MCN?" yes -> Generate System Generated 4MCN Form (Originator).
// Laid out like CS-027 "4M Change Application/Notification to ADGT", prefilled from the TEB entry.
export default function Form({ p, today }) {
    const tebUrl = useTebUrl();
    const { fourMItems, classifications, typesOfChange } = usePage().props.teb.options;
    const { data, setData, post, processing, errors } = useForm({
        title: p.title ?? '',
        change_items: [],
        classification: p.classification ?? '',
        types_of_change: p.types_of_change ?? [],
        date_written: today,
        effectivity_date: '',
        expiration_date: '',
        extension_no: '',
        before_change: p.current_status ?? '',
        after_change: p.change_description ?? '',
        reason: p.project_description ?? '',
        attachments_note: p.attachments.map((a) => `${a.type_label}: ${a.original_name}`).join('\n'),
    });
    const toggle = (key, value) => setData(key, data[key].includes(value) ? data[key].filter((v) => v !== value) : [...data[key], value]);
    const temporary = data.types_of_change.some((t) => t === 'temporary' || t === 'waiver');

    return (
        <TebLayout title="Generate 4MCN Form">
            <h1>4M Change Application/Notification to ADGT</h1>
            <form onSubmit={(e) => { e.preventDefault(); post(tebUrl.fourMcnStore(p.id)); }}>
                <div className="teb-card teb-grid">
                    <div className="teb-grid teb-g2">
                        <Field label="4M Change Title" htmlFor="title" error={errors.title}>
                            <input type="text" id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                        </Field>
                        <div className="teb-grid teb-g2">
                            <Field label="4MCN#"><input type="text" value="Auto-generated on save" disabled /></Field>
                            <Field label="TEB No"><input type="text" value={p.teb_no ?? ''} disabled /></Field>
                        </div>
                    </div>
                    <div className="teb-grid teb-g2">
                        <Field label="Change Classification (4M)" error={errors.change_items}>
                            <div className="teb-checks">
                                {Object.entries(fourMItems).map(([k, v]) => (
                                    <label key={k}><input type="checkbox" checked={data.change_items.includes(k)} onChange={() => toggle('change_items', k)} /> {v}</label>
                                ))}
                            </div>
                        </Field>
                        <Field label="Change Request" error={errors.classification}>
                            <div className="teb-checks">
                                {Object.entries(classifications).map(([k, v]) => (
                                    <label key={k}><input type="radio" name="classification" checked={data.classification === k} onChange={() => setData('classification', k)} required /> {v} Change Request</label>
                                ))}
                            </div>
                            <div className="teb-small teb-muted" style={{ marginTop: 4 }}>Major change requires FMEA document.</div>
                        </Field>
                    </div>
                    <div className="teb-grid teb-g3">
                        <Field label="Date Written" htmlFor="date_written" error={errors.date_written}>
                            <input type="date" id="date_written" value={data.date_written} onChange={(e) => setData('date_written', e.target.value)} required />
                        </Field>
                        <Field label="Effectivity Date" htmlFor="effectivity_date" error={errors.effectivity_date}>
                            <input type="date" id="effectivity_date" value={data.effectivity_date} onChange={(e) => setData('effectivity_date', e.target.value)} required />
                        </Field>
                        <Field label="Originator"><input type="text" value={p.owner_name ?? ''} disabled /></Field>
                    </div>
                    <Field label="This change is:" error={errors.types_of_change}>
                        <div className="teb-checks">
                            {Object.entries(typesOfChange).map(([k, v]) => (
                                <label key={k}><input type="checkbox" checked={data.types_of_change.includes(k)} onChange={() => toggle('types_of_change', k)} /> {v}</label>
                            ))}
                        </div>
                    </Field>
                    <div className="teb-grid teb-g2">
                        <Field label="Expiration Date (for temporary or waiver)" htmlFor="expiration_date" error={errors.expiration_date}>
                            <input type="date" id="expiration_date" value={temporary ? data.expiration_date : ''} disabled={!temporary}
                                onChange={(e) => setData('expiration_date', e.target.value)} />
                            {!temporary && <div className="teb-small teb-muted">N/A</div>}
                        </Field>
                        <Field label="No. of extension" error={errors.extension_no}>
                            <div className="teb-checks">
                                {[[1, '1st'], [2, '2nd'], [3, '3rd']].map(([n, l]) => (
                                    <label key={n}>
                                        <input type="checkbox" disabled={!temporary} checked={temporary && data.extension_no === n}
                                            onChange={() => setData('extension_no', data.extension_no === n ? '' : n)} /> {l}
                                    </label>
                                ))}
                            </div>
                        </Field>
                    </div>
                    <div className="teb-grid teb-g2">
                        <Field label="Current Status" htmlFor="before_change" error={errors.before_change}>
                            <textarea id="before_change" rows={6} value={data.before_change} onChange={(e) => setData('before_change', e.target.value)} required />
                        </Field>
                        <Field label="Change Description" htmlFor="after_change" error={errors.after_change}>
                            <textarea id="after_change" rows={6} value={data.after_change} onChange={(e) => setData('after_change', e.target.value)} required />
                        </Field>
                    </div>
                    <Field label="Justification" htmlFor="reason" error={errors.reason}>
                        <textarea id="reason" value={data.reason} onChange={(e) => setData('reason', e.target.value)} required />
                    </Field>
                    <Field label="Attachments" htmlFor="attachments_note" error={errors.attachments_note}>
                        <textarea id="attachments_note" value={data.attachments_note} onChange={(e) => setData('attachments_note', e.target.value)} />
                    </Field>
                </div>
                <div className="teb-row">
                    <button className="teb-btn" disabled={processing}>Generate 4MCN</button>
                    <Link className="teb-btn ghost" href={tebUrl.show(p.id)}>Back</Link>
                </div>
            </form>
        </TebLayout>
    );
}
