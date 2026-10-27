import TebLayout from '../../../Layouts/TebLayout';
import Filters from '../../../Components/Teb/Filters';
import ProjectTable from '../../../Components/Teb/ProjectTable';
import { useTebUrl } from '../../../Components/Teb/urls';

// Slide 3 "For Review (Process Engineering)" — TEB Admin only.
export default function Index({ projects, statuses, filters }) {
    const tebUrl = useTebUrl();
    return (
        <TebLayout title="For Review">
            <div className="teb-row teb-spread" style={{ marginBottom: 16 }}>
                <h1 style={{ margin: 0 }}>For Review <span className="teb-muted" style={{ fontWeight: 400 }}>· Process Engineering</span></h1>
                <a className="teb-btn ghost" href={tebUrl.export(filters)}>Extract report (CSV)</a>
            </div>
            <div className="teb-card">
                <Filters action={tebUrl.review()} filters={filters} statuses={statuses} />
                <ProjectTable projects={projects} mode="review" />
            </div>
        </TebLayout>
    );
}
