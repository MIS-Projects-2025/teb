import { Link, useForm, usePage } from '@inertiajs/react';
import TebLayout from '../../../Layouts/TebLayout';
import FormDetails from '../../../Components/Teb/FormDetails';
import Field from '../../../Components/Teb/Field';
import MultiSelect from '../../../Components/Teb/MultiSelect';
import { useTebUrl } from '../../../Components/Teb/urls';

// Schedule TEB Review: sends each reviewee (and the owner) the project details with a calendar invite.
export default function Schedule({ p, emails, emailSource }) {
    const tebUrl = useTebUrl();
    const { options } = usePage().props.teb;
    const { data, setData, post, processing, errors } = useForm({
        review_at: '', duration_minutes: 60, location: '', reviewees: [], notes: '',
    });
    const emailOptions = emails.map((e) => ({ id: e, name: e }));

    return (
        <TebLayout title="Schedule TEB Review">
            <h1>Schedule TEB Review</h1>
            <div className="teb-grid">
                <div className="teb-card"><h2>Registered project</h2><FormDetails p={p} options={options} /></div>
                <form className="teb-card teb-grid" style={{ alignContent: 'start' }}
                    onSubmit={(e) => { e.preventDefault(); post(tebUrl.schedule(p.id)); }}>
                    <h2>Calendar invite</h2>
                    <div className="teb-grid teb-g2">
                        <Field label="Date and time" htmlFor="review_at" error={errors.review_at}>
                            <input type="datetime-local" id="review_at" value={data.review_at} onChange={(e) => setData('review_at', e.target.value)} required />
                        </Field>
                        <Field label="Duration (minutes)" htmlFor="duration_minutes" error={errors.duration_minutes}>
                            <input type="number" id="duration_minutes" min={15} max={480} value={data.duration_minutes} onChange={(e) => setData('duration_minutes', e.target.value)} required />
                        </Field>
                    </div>
                    <Field label="Location / meeting link" htmlFor="location" error={errors.location}>
                        <input type="text" id="location" value={data.location} onChange={(e) => setData('location', e.target.value)} />
                    </Field>
                    <Field label="Reviewees (email)" htmlFor="reviewees" error={errors.reviewees ?? Object.entries(errors).find(([k]) => k.startsWith('reviewees.'))?.[1]
                        ?? (emails.length === 0 ? `Email accounts (${emailSource}) could not be loaded.` : null)}>
                        <MultiSelect id="reviewees" options={emailOptions} value={data.reviewees} showAll={false}
                            onChange={(list) => setData('reviewees', list)} placeholder="Select email(s)…" />
                        <div className="teb-small teb-muted">{data.reviewees.length} selected. The project owner is always invited. Each invite carries the project details and an .ics calendar file.</div>
                    </Field>
                    <Field label="Notes to reviewees" htmlFor="notes" error={errors.notes}>
                        <textarea id="notes" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                    </Field>
                    <div className="teb-row">
                        <button className="teb-btn" disabled={processing}>Send invite</button>
                        <Link className="teb-btn ghost" href={tebUrl.review()}>Back to list</Link>
                    </div>
                </form>
            </div>
        </TebLayout>
    );
}
