import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import TebLayout from '../../../Layouts/TebLayout';
import StatusChip from '../../../Components/Teb/StatusChip';
import FormDetails from '../../../Components/Teb/FormDetails';
import ApprovalSection from '../../../Components/Teb/ApprovalSection';
import { useTebUrl } from '../../../Components/Teb/urls';

const LOCKED = ['for_approval', 'approved'];

export default function Show({ p, isOwner, myTurn, nextNo }) {
    const tebUrl = useTebUrl();
    const { isAdmin, options } = usePage().props.teb;

    const steps = [
        ['Registered', true],
        ['Under review', p.status !== 'submitted_for_review'],
        ['Reviewed', ['pending_teb_number', 'reviewed', 'for_approval', 'returned', 'approved'].includes(p.status)],
        ['TEB No. assigned', !!p.teb_no],
        ['TEB form completed', !!p.completed_at && p.status !== 'returned'],
        ['Approved', p.status === 'approved'],
        ...(p.requires_4mcn ? [['4MCN generated', !!p.four_mcn]] : []),
    ];
    const now = steps.find(([, done]) => !done)?.[0];
    const adminCanAct = isAdmin && !LOCKED.includes(p.status);

    return (
        <TebLayout title={`${p.display_no} · ${p.title}`}>
            <div className="teb-row teb-spread" style={{ marginBottom: 12 }}>
                <div>
                    <div className="teb-small teb-muted">{p.category_label}</div>
                    <h1 style={{ margin: '2px 0 0' }}>{p.display_no} · {p.title}</h1>
                </div>
                <div className="teb-row">
                    <StatusChip status={p.status} />
                    <button className="teb-btn ghost" onClick={() => window.history.back()}>Back to list</button>
                </div>
            </div>

            <div className="teb-flow">
                {steps.map(([label, done]) => (
                    <span key={label} className={done ? 'done' : label === now ? 'now' : ''}>{done ? '✓ ' : ''}{label}</span>
                ))}
            </div>

            <div className="teb-grid teb-side">
                <div className="teb-card">
                    <div className="teb-row teb-spread" style={{ marginBottom: 12, alignItems: 'flex-start' }}>
                        <h2 style={{ margin: 0 }}>Project details</h2>
                        <div className="teb-row teb-noprint" style={{ gap: 8, justifyContent: 'flex-end' }}>
                            {isOwner && p.is_editable && (
                                <Link className="teb-icon-btn" href={tebUrl.edit(p.id)} title="Edit entry" aria-label="Edit entry">
                                    <PencilIcon />
                                </Link>
                            )}
                            {p.teb_no && <a className="teb-btn sm ghost" href={tebUrl.form(p.id)} target="_blank" rel="noreferrer">TEB Form (print / PDF)</a>}
                            {p.four_mcn && <a className="teb-btn sm ghost" href={tebUrl.fourMcnShow(p.id)} target="_blank" rel="noreferrer">4MCN Form {p.four_mcn.mcn_no}</a>}
                            {adminCanAct && <Link className="teb-btn sm ghost" href={tebUrl.schedule(p.id)}>Schedule TEB Review</Link>}
                            {isOwner && p.can_be_completed && <Link className="teb-btn sm" href={tebUrl.complete(p.id)}>Complete TEB form &amp; upload TEB Report</Link>}
                            {isOwner && p.needs_4mcn && <Link className="teb-btn sm" href={tebUrl.fourMcnCreate(p.id)}>Generate 4MCN Form</Link>}
                        </div>
                    </div>
                    <FormDetails p={p} options={options} />
                    {adminCanAct && (
                        <div className="teb-grid teb-g2 teb-noprint" style={{ marginTop: 20, paddingTop: 16, borderTop: '1px solid var(--line)' }}>
                            <StatusForm p={p} options={options} />
                            {!p.teb_no && <AssignForm p={p} nextNo={nextNo} />}
                        </div>
                    )}
                </div>
                <div>
                    <div className="teb-card">
                        <h2>Documents</h2>
                        {p.attachments.length === 0 && <p className="teb-muted">No documents yet.</p>}
                        {p.attachments.map((f) => (
                            <div key={f.id} className="teb-item">
                                <span className="teb-chip">{f.type_label}</span> <a href={f.url}>{f.original_name}</a>
                            </div>
                        ))}
                        {isOwner && p.status !== 'approved' && <UploadForm p={p} types={options.attachmentTypes} />}
                    </div>
                    <div className="teb-card">
                        <h2>TEB reviews</h2>
                        {p.reviews.length === 0 && <p className="teb-muted">No review scheduled yet.</p>}
                        {p.reviews.map((r) => (
                            <div key={r.id} className="teb-item">
                                <strong>{r.review_at}</strong> · {r.duration_minutes} min
                                <div className="teb-small teb-muted">{r.location || 'No location'} · {r.invites_sent} invite(s) sent</div>
                                {r.notes && <div className="teb-small">{r.notes}</div>}
                                <div className="teb-row teb-noprint" style={{ gap: 12, marginTop: 4 }}>
                                    <a className="teb-small" href={r.ics_url}>Add to calendar (.ics)</a>
                                    {isAdmin && (
                                        <Link as="button" method="post" href={tebUrl.resend(p.id, r.id)} preserveScroll className="teb-btn sm ghost"
                                            onBefore={() => window.confirm('Send this review invite again to all reviewees and the owner?')}>
                                            Resend invite
                                        </Link>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {p.approvals.length > 0 && (
                <div className="teb-card" id="approval">
                    <h2>Approval section</h2>
                    <ApprovalSection approvals={p.approvals} />
                    {myTurn && p.status === 'for_approval' && <DecisionForm myTurn={myTurn} />}
                </div>
            )}

            <div className="teb-card">
                <h2>History</h2>
                <ul className="teb-timeline">
                    {p.history.map((h) => (
                        <li key={h.id}>
                            <StatusChip status={h.status} label={h.label} /> <span className="teb-muted teb-small">· {h.at} · {h.by}</span>
                            {h.remarks && <div className="teb-small">{h.remarks}</div>}
                        </li>
                    ))}
                </ul>
            </div>
        </TebLayout>
    );
}

function StatusForm({ p, options }) {
    const tebUrl = useTebUrl();
    const { data, setData, post, processing, errors } = useForm({
        status: options.reviewStatuses.includes(p.status) ? p.status : 'open',
        remarks: '',
    });

    return (
        <form id="update-status" className="teb-grid" style={{ alignContent: 'start' }}
            onSubmit={(e) => { e.preventDefault(); post(tebUrl.status(p.id), { preserveScroll: true, onSuccess: () => setData('remarks', '') }); }}>
            <label className="teb-l" htmlFor="status">Update status (TEB Admin)</label>
            <div className="teb-row">
                <select id="status" value={data.status} onChange={(e) => setData('status', e.target.value)} style={{ maxWidth: 220 }}>
                    {options.reviewStatuses.map((s) => (
                        <option key={s} value={s} disabled={s === 'reviewed' && !!p.review_block}>
                            {options.statuses[s]}{s === 'reviewed' && p.review_block ? ' (after review)' : ''}
                        </option>
                    ))}
                </select>
                <button className="teb-btn" disabled={processing}>Update</button>
            </div>
            <input type="text" value={data.remarks} onChange={(e) => setData('remarks', e.target.value)} placeholder="Remarks (optional)" />
            {errors.status && <div className="teb-err">{errors.status}</div>}
            {p.review_block
                ? <div className="teb-small teb-muted">{p.review_block}</div>
                : !p.teb_no && <div className="teb-small teb-muted">Setting “Reviewed” before a TEB No. exists moves the entry to Pending TEB Number.</div>}
        </form>
    );
}

function AssignForm({ p, nextNo }) {
    const tebUrl = useTebUrl();
    const { post, processing } = useForm({});

    // TEB No. is system generated (yyyy-NNNNN); the admin only confirms it.
    return (
        <form className="teb-grid" style={{ alignContent: 'start' }}
            onSubmit={(e) => { e.preventDefault(); post(tebUrl.assign(p.id), { preserveScroll: true }); }}>
            <label className="teb-l">Assign TEB Number (TEB Admin)</label>
            <div className="teb-row">
                <span className="teb-chip" style={{ fontSize: 14, padding: '6px 12px' }}>Next: {nextNo}</span>
                <button className="teb-btn" disabled={processing}>Assign</button>
            </div>
            <div className="teb-small teb-muted">Numbers are auto-generated in order and restart each year.</div>
        </form>
    );
}

function UploadForm({ p, types }) {
    const tebUrl = useTebUrl();
    const { data, setData, post, processing, reset } = useForm({ type: 'other', file: null });
    const [key, setKey] = useState(0);

    return (
        <form className="teb-grid teb-noprint" style={{ marginTop: 12 }}
            onSubmit={(e) => {
                e.preventDefault();
                post(tebUrl.upload(p.id), { forceFormData: true, preserveScroll: true, onSuccess: () => { reset(); setKey(key + 1); } });
            }}>
            <div className="teb-row">
                <select value={data.type} onChange={(e) => setData('type', e.target.value)} style={{ maxWidth: 150 }}>
                    {Object.entries(types).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                </select>
                <input key={key} type="file" required onChange={(e) => setData('file', e.target.files[0])} style={{ flex: 1 }} />
            </div>
            <button className="teb-btn sm ghost" disabled={processing}>Upload</button>
        </form>
    );
}

function DecisionForm({ myTurn }) {
    const tebUrl = useTebUrl();
    const [remarks, setRemarks] = useState('');
    const [busy, setBusy] = useState(false);
    const send = (decision) => router.post(tebUrl.act(myTurn.id), { decision, remarks }, {
        preserveScroll: true,
        onStart: () => setBusy(true),
        onFinish: () => setBusy(false),
    });

    return (
        <div className="teb-grid teb-noprint" style={{ marginTop: 16, maxWidth: 560 }}>
            <label className="teb-l" htmlFor="remarks">Your decision as {myTurn.label}</label>
            <textarea id="remarks" value={remarks} onChange={(e) => setRemarks(e.target.value)} placeholder="Remarks (required when disapproving)" style={{ minHeight: 60 }} />
            <div className="teb-row">
                <button className="teb-btn ok" disabled={busy} onClick={() => send('approved')}>Approve</button>
                <button className="teb-btn bad" disabled={busy} onClick={() => send('rejected')}>Disapprove</button>
            </div>
        </div>
    );
}

function PencilIcon() {
    return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M12 20h9" />
            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
        </svg>
    );
}
