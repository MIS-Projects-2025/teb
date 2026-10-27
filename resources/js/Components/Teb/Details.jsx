export default function Details({ p }) {
    const rows = [
        ['TEB No', p.display_no],
        ['TEB Change Title', p.title],
        ['Category', p.category_label],
        ['Customer', p.customer_name || '—'],
        ['Change Classification', p.classification_label],
        ['Type of Change', p.types_of_change_label],
        ['Date Created', p.created_at],
        ['Affected Areas', p.affected_areas_label],
        ['Project Owner', `${p.owner_name} · ${p.owner_department ?? ''}`],
        ['Project Description', p.project_description],
        ['Current Status', p.current_status || '—'],
        ['Change Description', p.change_description || '—'],
    ];
    if (p.completed_at) {
        rows.push(['Completed', p.completed_at + (p.completion_remarks ? ` · ${p.completion_remarks}` : '')]);
        rows.push(['4MCN Required', (p.requires_4mcn ? 'Yes' : 'No') + (p.four_mcn ? ` · ${p.four_mcn.mcn_no}` : '')]);
    }

    return (
        <dl className="teb-kv">
            {rows.map(([k, v]) => [<dt key={`${k}-t`}>{k}</dt>, <dd key={`${k}-d`}>{v}</dd>])}
        </dl>
    );
}
