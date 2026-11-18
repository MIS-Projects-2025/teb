import { usePage } from '@inertiajs/react';
import TebLayout from '../../../Layouts/TebLayout';
import BmpMaintenance from '../../../Components/Teb/BmpMaintenance';

// BMP maintenance, and who can sign the approval section (that part follows the masterlist).
export default function Index({ departments, fixed, adminDepartments, jobLevelIds, jobLevelWords, executives = [], bmp = [] }) {
    const levels = usePage().props.teb.options.approvalLevels;
    const rule = Object.entries(levels)
        .map(([k, l]) => `${l}: job level ${(jobLevelIds[k] ?? []).join(', ') || '—'} or a job/position level named ${(jobLevelWords[k] ?? []).join(' / ')}`)
        .join('; ');

    return (
        <TebLayout title="TRB Settings">
            <h1>TRB Settings</h1>
            <BmpMaintenance rows={bmp} />
            <div className="teb-card">
                <h2>Who can sign</h2>
                <p className="teb-muted teb-small">
                    Approvers are taken from the masterlist automatically, so no names need to be set. For each department, any active employee
                    in that department (employee_department) with the matching level can sign: {rule}. Levels sign in order.
                    Bold departments are always routed ({fixed.join(', ')}); “Others” is the project owner's department when it is not one of these.
                    TRB Admins are employees in {adminDepartments.join(', ')}.
                </p>
                <div className="teb-tbl">
                    <table>
                        <thead><tr><th>Department</th>{Object.values(levels).map((l) => <th key={l}>{l}</th>)}</tr></thead>
                        <tbody>
                            {departments.map((d) => (
                                <tr key={d.id}>
                                    <td>{fixed.includes(d.name) ? <strong>{d.name}</strong> : d.name}</td>
                                    {Object.keys(levels).map((level) => {
                                        const list = d.candidates?.[level] ?? [];
                                        return (
                                            <td key={level}>
                                                {list.length
                                                    ? <span className="teb-chip teb-badge a-approved" title={list.map((c) => c.name).join('\n')}>{list.length} can sign</span>
                                                    : <span className="teb-chip teb-badge a-rejected">None in masterlist</span>}
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <p className="teb-small teb-muted">“None in masterlist” blocks routing for that department until someone with that level is added there.</p>
            </div>
            <div className="teb-card">
                <h2>Major changes: company-wide signers</h2>
                <p className="teb-muted teb-small">After every department signs a Major change, the Operation Director signs, then the President. Only employees with the Executive position level (id 35) are listed.</p>
                <div className="teb-tbl">
                    <table>
                        <thead><tr><th>Signer</th><th>Who can sign</th></tr></thead>
                        <tbody>
                            {executives.map((e) => (
                                <tr key={e.label}>
                                    <td><strong>{e.label}</strong></td>
                                    <td>
                                        {e.candidates.length
                                            ? e.candidates.map((c) => c.name).join(', ')
                                            : <span className="teb-chip teb-badge a-rejected">None in masterlist</span>}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </TebLayout>
    );
}
