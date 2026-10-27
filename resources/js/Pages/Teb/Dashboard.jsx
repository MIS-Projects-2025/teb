import { Link, usePage } from '@inertiajs/react';
import TebLayout from '../../Layouts/TebLayout';
import StatusChip, { STATUS_COLORS } from '../../Components/Teb/StatusChip';
import { useTebUrl } from '../../Components/Teb/urls';

export default function Dashboard({ total, byStatus, statuses, byCategory, pending4mcn, myProjects, myApprovals }) {
    const tebUrl = useTebUrl();
    const { categories, statuses: labels } = usePage().props.teb.options;

    return (
        <TebLayout title="Dashboard">
            <div className="teb-row teb-spread" style={{ marginBottom: 16 }}>
                <h1 style={{ margin: 0 }}>Dashboard</h1>
                <div className="teb-row">
                    <Link className="teb-btn" href={tebUrl.create()}>Register Project Entry</Link>
                    <a className="teb-btn ghost" href={tebUrl.export()}>Extract report (CSV)</a>
                </div>
            </div>

            <div className="teb-grid teb-stats">
                <Link className="teb-stat total" href={tebUrl.projects()}><div className="n">{total}</div><div className="l">Total TEB</div></Link>
                {statuses.map((s) => (
                    <Link key={s} className="teb-stat" style={{ borderTop: `4px solid ${STATUS_COLORS[s]}` }} href={tebUrl.projects({ status: s })}>
                        <div className="n" style={{ color: STATUS_COLORS[s] }}>{byStatus[s] ?? 0}</div>
                        <StatusChip status={s} />
                    </Link>
                ))}
                <Link className="teb-stat" style={{ borderTop: '4px solid #c2410c' }} href={tebUrl.projects({ status: 'approved' })}>
                    <div className="n" style={{ color: '#c2410c' }}>{pending4mcn}</div>
                    <span className="teb-chip teb-badge s-4mcn">4MCN Pending</span>
                </Link>
            </div>

            <div className="teb-card">
                <h2>By category</h2>
                <div className="teb-tbl">
                    <table>
                        <thead>
                            <tr><th>Category</th><th>Total</th>{statuses.map((s) => <th key={s}><StatusChip status={s} /></th>)}</tr>
                        </thead>
                        <tbody>
                            {Object.entries(categories).map(([key, label]) => {
                                const rows = byCategory[key] ?? {};
                                const sum = Object.values(rows).reduce((a, b) => a + b, 0);
                                return (
                                    <tr key={key}>
                                        <td><Link href={tebUrl.projects({ category: key })}>{label}</Link></td>
                                        <td><strong>{sum}</strong></td>
                                        {statuses.map((s) => <td key={s}>{rows[s] ?? 0}</td>)}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="teb-grid teb-g2">
                <div className="teb-card">
                    <div className="teb-row teb-spread"><h2>Waiting for my approval</h2><Link className="teb-small" href={tebUrl.approvals({ mine: 1 })}>All</Link></div>
                    {myApprovals.length === 0 && <p className="teb-muted">Nothing waiting on you.</p>}
                    {myApprovals.map((a) => (
                        <div key={a.id} className="teb-item">
                            <Link href={`${tebUrl.show(a.project_id)}#approval`}>{a.display_no} · {a.title}</Link>
                            <div className="teb-small teb-muted">{a.department_group} · {a.level_label}</div>
                        </div>
                    ))}
                </div>
                <div className="teb-card">
                    <div className="teb-row teb-spread"><h2>My recent projects</h2><Link className="teb-small" href={tebUrl.projects({ mine: 1 })}>All</Link></div>
                    {myProjects.length === 0 && <p className="teb-muted">You have not registered a project yet.</p>}
                    {myProjects.map((p) => (
                        <div key={p.id} className="teb-item teb-row teb-spread">
                            <Link href={tebUrl.show(p.id)}>{p.display_no} · {p.title}</Link>
                            <StatusChip status={p.status} />
                        </div>
                    ))}
                </div>
            </div>
        </TebLayout>
    );
}
