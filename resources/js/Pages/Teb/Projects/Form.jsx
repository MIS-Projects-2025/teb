import { Link, useForm, usePage } from '@inertiajs/react';
import TebLayout from '../../../Layouts/TebLayout';
import Field from '../../../Components/Teb/Field';
import MultiSelect from '../../../Components/Teb/MultiSelect';
import { useTebUrl } from '../../../Components/Teb/urls';

// Slide 2 "Register Project Entry" — also used for editing while the entry is still editable.
export default function Form({ p, customers, departments, ownerName, ownerDept, today }) {
    const tebUrl = useTebUrl();
    const { categories, classifications, typesOfChange } = usePage().props.teb.options;
    const editing = !!p;
    const hasFmea = editing && p.attachments.some((a) => a.type === 'fmea');

    const { data, setData, post, processing, errors } = useForm({
        title: p?.title ?? '',
        category: p?.category ?? '',
        customer_id: p?.customer_id ?? '',
        classification: p?.classification ?? '',
        types_of_change: p?.types_of_change ?? [],
        affected_areas: p?.affected_area_ids ?? [],
        project_description: p?.project_description ?? '',
        current_status: p?.current_status ?? '',
        change_description: p?.change_description ?? '',
        fmea: null,
        attachments: [],
        ...(editing ? { _method: 'put' } : {}),
    });

    const toggle = (key, value) => setData(key, data[key].includes(value) ? data[key].filter((v) => v !== value) : [...data[key], value]);

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
                        <Field label="TEB Change Title" htmlFor="title" error={errors.title}>
                            <input type="text" id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                        </Field>
                        <Field label="TEB No">
                            <input type="text" value={p?.teb_no || 'Auto-generated after review (e.g. 2026-00001)'} disabled />
                        </Field>
                    </div>
                    <div className="teb-grid teb-g3">
                        <Field label="Category" htmlFor="category" error={errors.category}>
                            <select id="category" value={data.category} onChange={(e) => setData('category', e.target.value)} required>
                                <option value="">Select…</option>
                                {Object.entries(categories).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                            </select>
                        </Field>
                        <Field label="Customer" htmlFor="customer_id" error={errors.customer_id ?? (customers.length === 0 ? 'Customer list (qdn_db.customer_list) could not be loaded.' : null)}>
                            <select id="customer_id" value={data.customer_id} onChange={(e) => setData('customer_id', e.target.value)}>
                                <option value="">Select…</option>
                                {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </Field>
                        <Field label="Date Created"><input type="text" value={p?.created_at ?? today} disabled /></Field>
                    </div>
                    <div className="teb-grid teb-g2">
                        <Field label="Change Classification" error={errors.classification ?? errors.fmea}>
                            <div className="teb-checks">
                                {Object.entries(classifications).map(([k, v]) => (
                                    <label key={k}>
                                        <input type="radio" name="classification" value={k} checked={data.classification === k} onChange={() => setData('classification', k)} required /> {v}
                                    </label>
                                ))}
                            </div>
                            {data.classification === 'major' && (
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
                    <div className="teb-grid teb-g2">
                        <Field label="Project Owner"><input type="text" value={ownerName ?? ''} disabled /></Field>
                        <Field label="Department"><input type="text" value={ownerDept ?? ''} disabled /></Field>
                    </div>
                    <Field label="Project Description" htmlFor="project_description" error={errors.project_description}>
                        <textarea id="project_description" value={data.project_description} onChange={(e) => setData('project_description', e.target.value)} required />
                    </Field>
                    <div className="teb-grid teb-g2">
                        <Field label="Current Status" htmlFor="current_status" error={errors.current_status}>
                            <textarea id="current_status" value={data.current_status} onChange={(e) => setData('current_status', e.target.value)} />
                        </Field>
                        <Field label="Change Description" htmlFor="change_description" error={errors.change_description}>
                            <textarea id="change_description" value={data.change_description} onChange={(e) => setData('change_description', e.target.value)} />
                        </Field>
                    </div>
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
