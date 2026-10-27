import { Link } from '@inertiajs/react';

// Renders a Laravel paginator's links (works for both v1 and v2+ paginator payloads).
export default function Pager({ paginator }) {
    const links = paginator?.links ?? paginator?.meta?.links;
    if (!links || (paginator.last_page ?? paginator.meta?.last_page) <= 1) return null;

    return (
        <div className="teb-pager teb-noprint">
            {links.map((l, i) => {
                const label = l.label.replace('&laquo;', '«').replace('&raquo;', '»');
                return l.url
                    ? <Link key={i} href={l.url} preserveScroll className={l.active ? 'on' : ''}>{label}</Link>
                    : <span key={i} className="off">{label}</span>;
            })}
        </div>
    );
}
