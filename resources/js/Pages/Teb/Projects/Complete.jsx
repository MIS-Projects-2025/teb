import { Link, useForm } from "@inertiajs/react";
import TebLayout from "../../../Layouts/TebLayout";
import Field from "../../../Components/Teb/Field";
import { useTebUrl } from "../../../Components/Teb/urls";

// "Generate System Generated TRB Form with Completed Status and upload the TRB Report".
export default function Complete({ p }) {
    const tebUrl = useTebUrl();
    const reports = p.attachments.filter((a) => a.type === "teb_report");
    const { data, setData, post, processing, errors, progress } = useForm({
        teb_report: null,
        requires_4mcn:
            p.requires_4mcn === null ? "" : p.requires_4mcn ? "1" : "0",
        completion_remarks: p.completion_remarks ?? "",
        customer_trial: null,
        residual_risks: "",
        summary_of_changes: "",
        training_plan: "",
        conclusion: "",
    });
    const trials = p.attachments.filter((a) => a.type === "customer_trial");
    const results = [
        ["residual_risks", "Residual risks"],
        ["summary_of_changes", "Summary of changes"],
        ["training_plan", "Training and documentation plan"],
        ["conclusion", "Conclusion and recommendation *"],
    ];

    // Errors that are not tied to a field on this page (e.g. no eligible approver in the masterlist).
    const fieldKeys = [
        "teb_report",
        "requires_4mcn",
        "completion_remarks",
        "customer_trial",
        ...results.map(([k]) => k),
    ];
    const otherErrors = Object.entries(errors)
        .filter(([k]) => !fieldKeys.includes(k))
        .map(([, v]) => v);

    return (
        <TebLayout title="Complete TRB Form">
            <h1>Step 6 · TRB Results · {p.teb_no}</h1>
            <p className="teb-muted">
                Upload the TRB Report (validation results) and summarize them
                for the board. The portal routes the results for TSPI approval.
            </p>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(tebUrl.complete(p.id), {
                        forceFormData: true,
                        preserveScroll: true,
                    });
                }}
            >
                <div className="teb-card teb-grid" style={{ maxWidth: 720 }}>
                    <Field
                        label="TRB Report"
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
                    {(p.uses_customer_product || trials.length > 0) && (
                        <Field
                            label="Customer trial authorization (the trial used customer product)"
                            htmlFor="customer_trial"
                            error={errors.customer_trial}
                        >
                            <input
                                type="file"
                                id="customer_trial"
                                onChange={(e) =>
                                    setData("customer_trial", e.target.files[0])
                                }
                            />
                            {trials.length > 0 && (
                                <div className="teb-small teb-muted">
                                    On file:{" "}
                                    {trials
                                        .map((r) => r.original_name)
                                        .join(", ")}
                                </div>
                            )}
                        </Field>
                    )}
                    {results.map(([k, label]) => (
                        <Field
                            key={k}
                            label={label}
                            htmlFor={k}
                            error={errors[k]}
                        >
                            <textarea
                                id={k}
                                value={data[k]}
                                onChange={(e) => setData(k, e.target.value)}
                                style={{ minHeight: 60 }}
                            />
                        </Field>
                    ))}
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
                            : "Submit results for TSPI approval"}
                    </button>
                    <Link className="teb-btn ghost" href={tebUrl.show(p.id)}>
                        Back
                    </Link>
                </div>
            </form>
        </TebLayout>
    );
}
