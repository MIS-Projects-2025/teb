import { Link, usePage } from '@inertiajs/react';
import TebLayout from '../../../Layouts/TebLayout';
import Field from '../../../Components/Teb/Field';
import { useTebUrl } from '../../../Components/Teb/urls';

const ADGT_SIGNERS = ['ADGT Mfg', 'ADGT PE', 'ADGT EE', 'ADGT QA'];
const DECISIONS = ['Approved without condition', 'Conditionally Approved', 'Disapproved'];
const EXTENSIONS = [[1, '1st'], [2, '2nd'], [3, '3rd']];

// System-generated CS-027 (Rev.2) form. Print or save as PDF from the browser.
export default function Print({ p }) {
    const tebUrl = useTebUrl();
    const { fourMItems, classifications, typesOfChange } = usePage().props.teb.options;
    const m = p.four_mcn;
    const temporary = m.types_of_change.some((t) => t === 'temporary' || t === 'waiver');

    return (
        <TebLayout title={m.mcn_no}>
            <div className="teb-row teb-spread teb-noprint" style={{ marginBottom: 12 }}>
                <Link className="teb-btn ghost" href={tebUrl.show(p.id)}>Back</Link>
                <button className="teb-btn" onClick={() => window.print()}>Print / Save as PDF</button>
            </div>
            <div className="teb-card teb-4m">
                <div className="teb-formhead">
                    <div><div className="teb-small teb-muted">Telford Engineering Board</div><h1 style={{ margin: 0 }}>4M CHANGE APPLICATION/NOTIFICATION TO ADGT</h1></div>
                    <div style={{ textAlign: 'right' }}>
                        <div><strong>TEB No:</strong> {p.teb_no}</div>
                        <div className="teb-small teb-muted">System generated {m.created_at}</div>
                    </div>
                </div>
                <div className="teb-grid teb-ro">
                    <div className="teb-grid teb-g2">
                        <Field label="4M Change Title"><Value>{m.title}</Value></Field>
                        <Field label="4MCN#"><Value>{m.mcn_no}</Value></Field>
                    </div>
                    <div className="teb-grid teb-g2">
                        <Field label="Change Classification (4M)">
                            <Checks options={Object.entries(fourMItems)} checked={(k) => m.change_items.includes(k)} />
                        </Field>
                        <Field label="Change Request">
                            <Checks type="radio" options={Object.entries(classifications).map(([k, v]) => [k, `${v} Change Request`])} checked={(k) => m.classification === k} />
                            <div className="teb-small teb-muted" style={{ marginTop: 4 }}>Major change requires FMEA document.</div>
                        </Field>
                    </div>
                    <div className="teb-grid teb-g3">
                        <Field label="Date Written"><Value>{m.date_written}</Value></Field>
                        <Field label="Effectivity Date"><Value>{m.effectivity_date}</Value></Field>
                        <Field label="Originator"><Value>{m.prepared_name || p.owner_name}</Value></Field>
                    </div>
                    <Field label="This change is:">
                        <Checks options={Object.entries(typesOfChange)} checked={(k) => m.types_of_change.includes(k)} />
                    </Field>
                    <div className="teb-grid teb-g2">
                        <Field label="Expiration Date (for temporary or waiver)"><Value>{temporary && m.expiration_date ? m.expiration_date : 'N/A'}</Value></Field>
                        <Field label="No. of extension">
                            <Checks options={EXTENSIONS} checked={(n) => temporary && m.extension_no === n} />
                        </Field>
                    </div>
                    <div className="teb-grid teb-g2">
                        <Field label="Current Status"><Value multi>{m.current_status}</Value></Field>
                        <Field label="Change Description"><Value multi>{m.change_description}</Value></Field>
                    </div>
                    <Field label="Justification"><Value multi>{m.justification}</Value></Field>
                    <Field label="Attachments"><Value multi>{m.attachments_note}</Value></Field>

                    <h2 className="teb-print-break" style={{ margin: '8px 0 0' }}>Approvals</h2>
                    <div className="teb-grid teb-adgt">
                        {ADGT_SIGNERS.map((s) => (
                            <div key={s} className="teb-adgt-box">
                                <div className="teb-adgt-sign" />
                                <div className="teb-adgt-name">{s}</div>
                                <div className="teb-small teb-muted">Signature / Date</div>
                                <Checks vertical options={DECISIONS.map((d) => [d, d])} checked={() => false} />
                            </div>
                        ))}
                    </div>
                </div>
                <div className="teb-row teb-spread teb-small teb-muted teb-formfoot">
                    <span>TELFORD SVC PHILS., INC</span>
                    <span>CS-027 (Rev.2)</span>
                </div>
            </div>
        </TebLayout>
    );
}

function Checks({ options, checked, type = 'checkbox', vertical }) {
    return (
        <div className="teb-checks" style={vertical ? { flexDirection: 'column', gap: 4 } : undefined}>
            {options.map(([k, v]) => (
                <label key={k}><input type={type} checked={checked(k)} disabled readOnly /> {v}</label>
            ))}
        </div>
    );
}

function Value({ children, multi }) {
    const empty = children === null || children === undefined || children === '';
    return <div className={`teb-ro-box${multi ? ' multi' : ''}${empty ? ' teb-muted' : ''}`}>{empty ? '—' : children}</div>;
}
