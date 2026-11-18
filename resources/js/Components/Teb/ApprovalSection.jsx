import { usePage } from '@inertiajs/react';

// Slide 4: approval section at the bottom of the registered project.
// One column per department: Manufacturing, PE, EE, QA, QMS and Others (the owner's department).
// Either a Supervisor, Section Head or Manager of that department signs its portion.
// Major changes add Operation Director, then President, on a second row once every department has signed.
export default function ApprovalSection({ approvals }) {
    const executives = approvals.filter((a) => a.executive);
    const columns = [];
    approvals.filter((a) => !a.executive).forEach((a) => {
        let c = columns.find((x) => x.group === a.department_group);
        if (!c) columns.push((c = { group: a.department_group, rows: [] }));
        c.rows.push(a);
    });
    if (!columns.some((c) => c.group.startsWith('Others'))) columns.push({ group: 'Others', rows: [] });

    return (
        <div>
            <div className="teb-small teb-muted" style={{ marginBottom: 8 }}>Either a Supervisor, Section Head or Manager can sign each portion.</div>
            <div className="teb-sign" style={{ '--cols': columns.length }}>
                {columns.map((c) => {
                    const [title, dept] = c.group.startsWith('Others: ') ? ['Others', c.group.slice(8)] : [c.group, null];
                    return (
                        <div key={c.group} className="teb-sign-col">
                            <div className="teb-sign-head">{title}{dept && <div className="teb-small">{dept}</div>}</div>
                            {c.rows.length === 0 && <div className="teb-sign-body teb-muted">N/A</div>}
                            {c.rows.map((a) => (
                                <div key={a.id} className="teb-sign-body">
                                    {a.level !== 'any' && <div className="teb-small teb-muted">{a.level_label}</div>}
                                    <div className="teb-sign-name">{a.approver_name || <span className="teb-muted">Pending signature</span>}</div>
                                    <span className={`teb-chip teb-badge a-${a.status}`}>
                                        {a.status === 'rejected' ? 'Disapproved' : a.status[0].toUpperCase() + a.status.slice(1)}
                                    </span>
                                    <div className="teb-small teb-muted">{a.acted_at ?? 'Date: —'}</div>
                                    {a.remarks && <div className="teb-small teb-muted">{a.remarks}</div>}
                                </div>
                            ))}
                        </div>
                    );
                })}
            </div>
            {executives.length > 0 && (
                <>
                    <div className="teb-small teb-muted" style={{ margin: '12px 0 8px' }}>Major change: signed after all departments, Operation Director first, then President.</div>
                    <div className="teb-sign" style={{ gridTemplateColumns: `repeat(${executives.length}, minmax(0, 1fr))`, maxWidth: `${executives.length * 20}%` }}>
                        {executives.map((a) => (
                            <div key={a.id} className="teb-sign-col">
                                <div className="teb-sign-head">{a.department_group}</div>
                                <div className="teb-sign-body">
                                    <div className="teb-sign-name">{a.approver_name || <span className="teb-muted">Pending signature</span>}</div>
                                    <span className={`teb-chip teb-badge a-${a.status}`}>
                                        {a.status === 'rejected' ? 'Disapproved' : a.status[0].toUpperCase() + a.status.slice(1)}
                                    </span>
                                    <div className="teb-small teb-muted">{a.acted_at ?? 'Date: —'}</div>
                                    {a.remarks && <div className="teb-small teb-muted">{a.remarks}</div>}
                                </div>
                            </div>
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}

// Step 4 trial plan approval and step 6 results / TSPI approval, newest round first.
export function ApprovalRounds({ approvals, open, after, print = false }) {
    const { roundLabels } = usePage().props.teb.options;
    const rounds = ['results', 'plan'].filter((r) => approvals.some((a) => (a.round ?? 'results') === r));

    return rounds.map((round) => (
        <div key={round} className={print ? '' : 'teb-card'} id={`approval-${round}`}>
            <h2 className={print ? 'teb-print-break' : ''} style={print ? { marginTop: 20 } : undefined}>
                Approval Section: {roundLabels[round]}{round === open && !print ? <span className="teb-chip" style={{ marginLeft: 8 }}>In progress</span> : null}
            </h2>
            <ApprovalSection approvals={approvals.filter((a) => (a.round ?? 'results') === round)} />
            {after?.(round)}
        </div>
    ));
}
