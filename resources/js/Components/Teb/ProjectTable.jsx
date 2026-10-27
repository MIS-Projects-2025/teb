import { Link } from '@inertiajs/react';
import StatusChip from './StatusChip';
import Pager from './Pager';
import { useTebUrl } from './urls';

// Columns from slides 3 and 4. mode: 'review' | 'approval' | undefined
export default function ProjectTable({ projects, mode }) {
    const tebUrl = useTebUrl();
    const rows = projects.data ?? projects;

    return (
        <>
            <div className="teb-tbl">
                <table>
                    <thead>
                        <tr>
                            <th>TEB No</th><th>Date Created</th><th>TEB Change Title</th><th>Customer</th><th>Project Description</th>
                            <th>Project Owner</th><th>Classification / Type</th><th>Affected Areas</th><th>Status</th>
                            <th className="teb-noprint">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td colSpan={10} className="teb-muted" style={{ textAlign: 'center', padding: 28 }}>No TEB entries found.</td></tr>
                        )}
                        {rows.map((r) => (
                            <tr key={r.id}>
                                <td style={{ whiteSpace: 'nowrap' }}>{r.display_no}</td>
                                <td style={{ whiteSpace: 'nowrap' }}>{r.created_at}</td>
                                <td style={{ minWidth: 180 }}>
                                    <Link href={tebUrl.show(r.id)}>{r.title}</Link>
                                    <div className="teb-small teb-muted">{r.category_label}</div>
                                </td>
                                <td>{r.customer_name}</td>
                                <td className="teb-small" style={{ minWidth: 180 }}>{truncate(r.project_description, 110)}</td>
                                <td>{r.owner_name}<div className="teb-small teb-muted">{r.owner_department}</div></td>
                                <td>{r.classification_label}<div className="teb-small teb-muted">{r.types_of_change_label}</div></td>
                                <td className="teb-small">{r.affected_areas_label}</td>
                                <td>
                                    <StatusChip status={r.status} />
                                    {r.needs_4mcn && <div className="teb-small" style={{ color: 'var(--warn)', marginTop: 4 }}>4MCN pending</div>}
                                </td>
                                <td className="teb-noprint">
                                    <div style={{ display: 'flex', flexDirection: 'column', gap: 4, whiteSpace: 'nowrap' }}>
                                        <Link className="teb-btn sm ghost" href={tebUrl.show(r.id)}>View</Link>
                                        {mode === 'review' && (
                                            <>
                                                <Link className="teb-btn sm ghost" href={tebUrl.schedule(r.id)}>Schedule</Link>
                                                <Link className="teb-btn sm ghost" href={`${tebUrl.show(r.id)}#update-status`}>Status</Link>
                                            </>
                                        )}
                                        {mode === 'approval' && <Link className="teb-btn sm" href={`${tebUrl.show(r.id)}#approval`}>Approve</Link>}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pager paginator={projects} />
        </>
    );
}

function truncate(s, n) {
    return s && s.length > n ? `${s.slice(0, n)}…` : s;
}
