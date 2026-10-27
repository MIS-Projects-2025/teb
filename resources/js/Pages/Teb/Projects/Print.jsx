import { Link, usePage } from '@inertiajs/react';
import TebLayout from '../../../Layouts/TebLayout';
import FormDetails from '../../../Components/Teb/FormDetails';
import ApprovalSection from '../../../Components/Teb/ApprovalSection';
import { useTebUrl } from '../../../Components/Teb/urls';

// System-generated TEB form. Print or save as PDF from the browser.
export default function Print({ p, generatedAt }) {
    const tebUrl = useTebUrl();
    const { options } = usePage().props.teb;
    return (
        <TebLayout title={`TEB Form ${p.display_no}`}>
            <div className="teb-row teb-spread teb-noprint" style={{ marginBottom: 12 }}>
                <Link className="teb-btn ghost" href={tebUrl.show(p.id)}>Back</Link>
                <button className="teb-btn" onClick={() => window.print()}>Print / Save as PDF</button>
            </div>
            <div className="teb-card">
                <div className="teb-formhead">
                    <div><div className="teb-small teb-muted">Telford Engineering Board</div><h1 style={{ margin: 0 }}>TEB FORM</h1></div>
                    <div style={{ textAlign: 'right' }}>
                        <div><strong>TEB No:</strong> {p.display_no}</div>
                        <div><strong>Status:</strong> {p.completed_at ? 'COMPLETED' : p.status_label.toUpperCase()}</div>
                        <div className="teb-small teb-muted">System generated {generatedAt}</div>
                    </div>
                </div>
                <FormDetails p={p} options={options} />
                <div className="teb-ro" style={{ marginTop: 16 }}>
                    <label className="teb-l">Documents</label>
                    <div className="teb-ro-box">
                        {p.attachments.length
                            ? p.attachments.map((f) => <span key={f.id} className="teb-chip">{f.type_label}: {f.original_name}</span>)
                            : <span className="teb-muted">—</span>}
                    </div>
                </div>
                {p.approvals.length > 0 && (
                    <>
                        <h2 className="teb-print-break" style={{ marginTop: 20 }}>Approval</h2>
                        <ApprovalSection approvals={p.approvals} />
                    </>
                )}
            </div>
        </TebLayout>
    );
}
