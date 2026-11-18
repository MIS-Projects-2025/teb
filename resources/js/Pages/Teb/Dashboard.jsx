import { Link, usePage } from '@inertiajs/react';
import TebLayout from '../../Layouts/TebLayout';
import StatusChip, { STATUS_COLORS } from '../../Components/Teb/StatusChip';
import { useTebUrl } from '../../Components/Teb/urls';

// TRB dashboard: headline figures, what needs me, where the entries are in the process, and the BMP mix.
export default function Dashboard({ total, kpis, byStatus, pipeline, byDriver, pending4mcn, myProjects, myApprovals }) {
    const tebUrl = useTebUrl();
    const { emp_data: emp, teb } = usePage().props;
    const labels = teb.options.statuses;
    const maxDriver = Math.max(1, ...byDriver.map((d) => d.total));

    const tiles = [
        { label: 'Total entries', value: total, note: 'All registered', href: tebUrl.projects(), accent: 'var(--brand)' },
        { label: 'Active', value: kpis.active, note: 'Not yet closed', accent: '#2563eb' },
        { label: 'Awaiting approval', value: kpis.approval, note: 'Trial plan or results', href: tebUrl.approvals(), accent: '#7c3aed' },
        { label: 'In implementation', value: kpis.implementation, note: 'Steps 7 to 10', accent: '#0f766e' },
        { label: '4MCN pending', value: pending4mcn, note: 'Needed, not generated', accent: '#c2410c' },
        { label: 'Closed', value: kpis.closed, note: 'Project closed', href: tebUrl.projects({ status: 'closed' }), accent: 'var(--ok)' },
    ];

    return (
        <TebLayout title="Dashboard">
            <div className="teb-row teb-spread teb-dash-head">
                <div>
                    <h1 style={{ margin: 0 }}>Dashboard</h1>
                    <div className="teb-muted">Welcome{emp?.emp_name ? `, ${emp.emp_name}` : ''}. Here is where the Technical Review Board entries stand.</div>
                </div>
                <div className="teb-row">
                    <a className="teb-btn ghost" href={tebUrl.export()}>Export CSV</a>
                    <Link className="teb-btn" href={tebUrl.create()}>Register Project Entry</Link>
                </div>
            </div>

            <div className="teb-kpis">
                {tiles.map((t) => {
                    const body = (
                        <>
                            <div className="teb-kpi-l">{t.label}</div>
                            <div className="teb-kpi-n">{t.value}</div>
                            <div className="teb-kpi-note">{t.note}</div>
                        </>
                    );
                    return t.href
                        ? <Link key={t.label} className="teb-kpi" href={t.href} style={{ '--accent': t.accent }}>{body}</Link>
                        : <div key={t.label} className="teb-kpi" style={{ '--accent': t.accent }}>{body}</div>;
                })}
            </div>

            <div className="teb-grid teb-g2">
                <div className="teb-card teb-panel">
                    <div className="teb-panel-head">
                        <h2>Waiting for my approval {myApprovals.length > 0 && <span className="teb-count">{myApprovals.length}</span>}</h2>
                        <Link className="teb-small" href={tebUrl.approvals({ mine: 1 })}>View all</Link>
                    </div>
                    {myApprovals.length === 0 && <div className="teb-empty">Nothing is waiting on you.</div>}
                    {myApprovals.map((a) => (
                        <Link key={a.id} className="teb-line" href={`${tebUrl.show(a.project_id)}#approval`}>
                            <div className="teb-line-main">
                                <div className="teb-line-no">{a.display_no}</div>
                                <div className="teb-line-title">{a.title}</div>
                            </div>
                            <div className="teb-line-side teb-small teb-muted">{a.round_label}<br />{a.department_group} · {a.level_label}</div>
                        </Link>
                    ))}
                </div>
                <div className="teb-card teb-panel">
                    <div className="teb-panel-head">
                        <h2>My recent projects</h2>
                        <Link className="teb-small" href={tebUrl.projects({ mine: 1 })}>View all</Link>
                    </div>
                    {myProjects.length === 0 && <div className="teb-empty">You have not registered a project yet.</div>}
                    {myProjects.map((p) => (
                        <Link key={p.id} className="teb-line" href={tebUrl.show(p.id)}>
                            <div className="teb-line-main">
                                <div className="teb-line-no">{p.display_no}</div>
                                <div className="teb-line-title">{p.title}</div>
                            </div>
                            <div className="teb-line-side"><StatusChip status={p.status} /></div>
                        </Link>
                    ))}
                </div>
            </div>

            <div className="teb-card">
                <div className="teb-panel-head"><h2>Process pipeline</h2><Link className="teb-small" href={tebUrl.projects()}>Open TRB Register</Link></div>
                <div className="teb-pipe">
                    {pipeline.map((ph) => {
                        const sum = ph.statuses.reduce((n, s) => n + (byStatus[s] ?? 0), 0);
                        return (
                            <div key={ph.label} className="teb-pipe-col">
                                <div className="teb-pipe-head"><span>{ph.label}</span><strong>{sum}</strong></div>
                                {ph.statuses.map((s) => {
                                    const n = byStatus[s] ?? 0;
                                    return (
                                        <Link key={s} className={`teb-pipe-row${n ? '' : ' zero'}`} href={tebUrl.projects({ status: s })}>
                                            <span className="dot" style={{ background: STATUS_COLORS[s] }} />
                                            <span className="lbl">{labels[s] ?? s}</span>
                                            <span className="n">{n}</span>
                                        </Link>
                                    );
                                })}
                            </div>
                        );
                    })}
                </div>
            </div>

            <div className="teb-card">
                <div className="teb-panel-head">
                    <h2>By BMP driver</h2>
                    <div className="teb-small teb-muted"><span className="teb-key major" /> Major <span className="teb-key minor" style={{ marginLeft: 10 }} /> Minor</div>
                </div>
                <div className="teb-bars">
                    {byDriver.map((d) => (
                        <Link key={d.key} className="teb-bar-row" href={tebUrl.projects({ category: d.key })}>
                            <span className="lbl"><strong>{d.key}</strong> {d.label}</span>
                            <span className="track">
                                <span className="major" style={{ width: `${(d.major / maxDriver) * 100}%` }} />
                                <span className="minor" style={{ width: `${(d.minor / maxDriver) * 100}%` }} />
                            </span>
                            <span className="n">{d.total}<span className="teb-small teb-muted"> ({d.major} / {d.minor})</span></span>
                        </Link>
                    ))}
                </div>
                <p className="teb-small teb-muted" style={{ margin: '10px 0 0' }}>An entry counts under every driver it checked.</p>
            </div>
        </TebLayout>
    );
}
