import { Link, usePage } from '@inertiajs/react';
import TebLayout from '../../../Layouts/TebLayout';
import ProjectTable from '../../../Components/Teb/ProjectTable';
import { useTebUrl } from '../../../Components/Teb/urls';

// Slide 4 "For approval" list.
export default function Index({ projects, mine }) {
    const tebUrl = useTebUrl();
    const { isAdmin } = usePage().props.teb;

    return (
        <TebLayout title="For Approval">
            <div className="teb-row teb-spread" style={{ marginBottom: 16 }}>
                <h1 style={{ margin: 0 }}>For Approval</h1>
                {isAdmin && (
                    <div className="teb-row">
                        <Link className={`teb-btn sm ${mine ? 'ghost' : ''}`} href={tebUrl.approvals()}>All in approval</Link>
                        <Link className={`teb-btn sm ${mine ? '' : 'ghost'}`} href={tebUrl.approvals({ mine: 1 })}>Assigned to me</Link>
                    </div>
                )}
            </div>
            <div className="teb-card"><ProjectTable projects={projects} mode="approval" /></div>
        </TebLayout>
    );
}
