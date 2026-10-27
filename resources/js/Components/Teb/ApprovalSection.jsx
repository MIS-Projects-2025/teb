import { usePage } from "@inertiajs/react";

// Slide 4: approval section at the bottom of the registered project.
// One column per department: Manufacturing, PE, EE, QA and Others (the owner's department).
// Either a Supervisor, Section Head or Manager of that department signs its portion.
export default function ApprovalSection({ approvals }) {
    const columns = [];
    approvals.forEach((a) => {
        let c = columns.find((x) => x.group === a.department_group);
        if (!c) columns.push((c = { group: a.department_group, rows: [] }));
        c.rows.push(a);
    });
    if (!columns.some((c) => c.group.startsWith("Others")))
        columns.push({ group: "Others", rows: [] });

    return (
        <div>
            <div className="teb-small teb-muted" style={{ marginBottom: 8 }}>
                Either a Supervisor, Section Head or Manager can sign each
                portion.
            </div>
            <div className="teb-sign">
                {columns.map((c) => {
                    const [title, dept] = c.group.startsWith("Others: ")
                        ? ["Others", c.group.slice(8)]
                        : [c.group, null];
                    return (
                        <div key={c.group} className="teb-sign-col">
                            <div className="teb-sign-head">
                                {title}
                                {dept && (
                                    <div className="teb-small">{dept}</div>
                                )}
                            </div>
                            {c.rows.length === 0 && (
                                <div className="teb-sign-body teb-muted">
                                    N/A
                                </div>
                            )}
                            {c.rows.map((a) => (
                                <div key={a.id} className="teb-sign-body">
                                    {a.level !== "any" && (
                                        <div className="teb-small teb-muted">
                                            {a.level_label}
                                        </div>
                                    )}
                                    <div className="teb-sign-name">
                                        {a.approver_name || (
                                            <span className="teb-muted">
                                                Pending signature
                                            </span>
                                        )}
                                    </div>
                                    <span
                                        className={`teb-chip teb-badge a-${a.status}`}
                                    >
                                        {a.status === "rejected"
                                            ? "Disapproved"
                                            : a.status[0].toUpperCase() +
                                              a.status.slice(1)}
                                    </span>
                                    <div className="teb-small teb-muted">
                                        {a.acted_at ?? "Date: —"}
                                    </div>
                                    {a.remarks && (
                                        <div className="teb-small teb-muted">
                                            {a.remarks}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
