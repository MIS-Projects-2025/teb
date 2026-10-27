import { Link, useForm } from "@inertiajs/react";
import TebLayout from "../../../Layouts/TebLayout";
import Field from "../../../Components/Teb/Field";
import { useTebUrl } from "../../../Components/Teb/urls";

// "Generate System Generated TEB Form with Completed Status and upload the TEB Report".
export default function Complete({ p }) {
    const tebUrl = useTebUrl();
    const reports = p.attachments.filter((a) => a.type === "teb_report");
    const { data, setData, post, processing, errors, progress } = useForm({
        teb_report: null,
        requires_4mcn:
            p.requires_4mcn === null ? "" : p.requires_4mcn ? "1" : "0",
        completion_remarks: p.completion_remarks ?? "",
    });

    // Errors that are not tied to a field on this page (e.g. no eligible approver in the masterlist).
    const otherErrors = Object.entries(errors)
        .filter(
            ([k]) =>
                !["teb_report", "requires_4mcn", "completion_remarks"].includes(
                    k,
                ),
        )
        .map(([, v]) => v);

    return (
        <TebLayout title="Complete TEB Form">
            <h1>Complete TEB Form · {p.teb_no}</h1>
            <p className="teb-muted">
                Upload the TEB Report and confirm completion. The portal then
                generates the TEB form with Completed status and routes it for
                approval.
            </p>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(tebUrl.complete(p.id), { forceFormData: true });
                }}
            >
                <div className="teb-card teb-grid" style={{ maxWidth: 720 }}>
                    <Field
                        label="TEB Report"
                        htmlFor="teb_report"
                        error={errors.teb_report}
                    >
                        <input
                            type="file"
                            id="teb_report"
                            onChange={(e) =>
                                setData("teb_report", e.target.files[0])
                            }
                        />
                        {reports.length > 0 && (
                            <div className="teb-small teb-muted">
                                On file:{" "}
                                {reports.map((r) => r.original_name).join(", ")}
                            </div>
                        )}
                    </Field>
                    <Field
                        label="Does this change need a 4M Change Notice (4MCN)?"
                        error={errors.requires_4mcn}
                    >
                        <div className="teb-checks">
                            <label>
                                <input
                                    type="radio"
                                    name="requires_4mcn"
                                    checked={data.requires_4mcn === "1"}
                                    onChange={() =>
                                        setData("requires_4mcn", "1")
                                    }
                                    required
                                />{" "}
                                Yes, generate a 4MCN after approval
                            </label>
                            <label>
                                <input
                                    type="radio"
                                    name="requires_4mcn"
                                    checked={data.requires_4mcn === "0"}
                                    onChange={() =>
                                        setData("requires_4mcn", "0")
                                    }
                                />{" "}
                                No
                            </label>
                        </div>
                    </Field>
                    <Field
                        label="Completion remarks"
                        htmlFor="completion_remarks"
                        error={errors.completion_remarks}
                    >
                        <textarea
                            id="completion_remarks"
                            value={data.completion_remarks}
                            onChange={(e) =>
                                setData("completion_remarks", e.target.value)
                            }
                        />
                    </Field>
                </div>

                {otherErrors.length > 0 && (
                    <div className="teb-flash bad" style={{ maxWidth: 720 }}>
                        {otherErrors.map((e, i) => (
                            <div key={i}>{e}</div>
                        ))}
                    </div>
                )}
                <div className="teb-row">
                    <button className="teb-btn" disabled={processing}>
                        {processing
                            ? `Submitting…${progress ? ` ${progress.percentage}%` : ""}`
                            : "Complete and route for approval"}
                    </button>
                    <Link className="teb-btn ghost" href={tebUrl.show(p.id)}>
                        Back
                    </Link>
                </div>
            </form>
        </TebLayout>
    );
}
