import { Head, Link, usePage } from '@inertiajs/react';
import { useTebUrl } from '../Components/Teb/urls';
import '../../css/teb.css';

export default function TebLayout({ title, children }) {
    const tebUrl = useTebUrl();
    const { props, url } = usePage();
    const { emp_data: emp, flash = {}, errors = {}, teb } = props;
    const base = teb?.base ?? '';
    const path = url.split('?')[0].slice(base.length) || '/';
    const query = url.includes('?') ? url.split('?')[1] : '';
    const mine = /(^|&)mine=1/.test(query);
    const errorList = Object.values(errors);

    const nav = [
        { href: tebUrl.dashboard(), label: 'Dashboard', on: path === '/' },
        { href: tebUrl.create(), label: 'Register Project', on: path === '/projects/create' },
        { href: tebUrl.projects({ mine: 1 }), label: 'My Projects', on: path === '/projects' && mine },
        { href: tebUrl.projects(), label: 'TEB Register', on: path === '/projects' && !mine },
        teb?.isAdmin && { href: tebUrl.review(), label: 'For Review', on: path.startsWith('/review') },
        { href: tebUrl.approvals(), label: 'For Approval', on: path.startsWith('/approvals') },
        teb?.isAdmin && { href: tebUrl.settings(), label: 'Settings', on: path.startsWith('/settings') },
    ].filter(Boolean);

    return (
        <div className="teb">
            <Head title={title ? `${title} · TEB Portal` : 'TEB Portal'} />
            <header className="teb-top">
                <Link className="teb-brand" href={tebUrl.dashboard()}>TEB Portal</Link>
                <nav>
                    {nav.map((n) => (
                        <Link key={n.label} href={n.href} className={n.on ? 'on' : ''}>{n.label}</Link>
                    ))}
                </nav>
                <div className="teb-who teb-row">
                    <span>{emp?.emp_name}{teb?.isAdmin ? ' · TEB Admin' : ''}</span>
                    {teb?.logout && <a href={teb.logout} style={{ color: '#fff' }}>Log out</a>}
                </div>
            </header>
            <main>
                {flash.success && <div className="teb-flash teb-noprint">{flash.success}</div>}
                {flash.error && <div className="teb-flash bad teb-noprint">{flash.error}</div>}
                {errorList.length > 0 && (
                    <div className="teb-flash bad teb-noprint">{errorList.map((e, i) => <div key={i}>{e}</div>)}</div>
                )}
                {children}
            </main>
        </div>
    );
}
