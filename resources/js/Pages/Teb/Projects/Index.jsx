import { Link } from '@inertiajs/react';
import TebLayout from '../../../Layouts/TebLayout';
import Filters from '../../../Components/Teb/Filters';
import ProjectTable from '../../../Components/Teb/ProjectTable';
import { useTebUrl } from '../../../Components/Teb/urls';

export default function Index({ projects, mine, filters }) {
    const tebUrl = useTebUrl();
    const title = mine ? 'My TEB Projects' : 'TEB Register';
    const keep = mine ? { mine: 1 } : {};

    return (
        <TebLayout title={title}>
            <div className="teb-row teb-spread" style={{ marginBottom: 16 }}>
                <h1 style={{ margin: 0 }}>{title}</h1>
                <div className="teb-row">
                    <a className="teb-btn ghost" href={tebUrl.export({ ...keep, ...filters })}>Extract report (CSV)</a>
                    <Link className="teb-btn" href={tebUrl.create()}>Register Project Entry</Link>
                </div>
            </div>
            <div className="teb-card">
                <Filters action={tebUrl.projects()} filters={filters} keep={keep} dates />
                <ProjectTable projects={projects} />
            </div>
        </TebLayout>
    );
}
