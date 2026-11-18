import { useForm, usePage } from "@inertiajs/react";
import { useState } from "react";
import { useTebUrl } from "./urls";

// "What happens next" card on the project page: the open step of the TRB process and, for whoever
// acts on it, the form for that step. Steps 1-3 use the review forms; 6 uses the Complete page.
export default function NextStep({ p, isOwner, stage }) {
    const { isAdmin } = usePage().props.teb;
    const s = p.status;

    const card = (title, text, form = null) => (
        <div className="teb-card teb-next teb-noprint">
            <div className="teb-small teb-muted">Next step</div>
            <h2 style={{ margin: "2px 0 6px" }}>{title}</h2>
            {text && (
                <p className="teb-muted teb-small" style={{ marginTop: 0 }}>
                    {text}
                </p>
            )}
            {form}
        </div>
    );

    switch (s) {
        case "submitted_for_review":
        case "open":
        case "hold":
        case "ongoing":
            return card(
                "2–3. TRB review and feasibility (Gate 1)",
                "The TRB Coordinator checks the submission and schedules the review through CMC. The board then records the decision: Feasible, Revise or Close, with its assessment.",
            );
        case "revise":
            return card(
                "3. Revise",
                isOwner
                    ? "Edit the entry (pencil icon) to address the remarks. Saving resubmits it for review."
                    : "Waiting for the originator to revise and resubmit.",
            );
        case "pending_teb_number":
            return card(
                "3. Feasible: assign TRB No.",
                "The TRB Admin assigns the TRB Number.",
            );
        case "reviewed":
        case "plan_returned":
            return card(
                s === "reviewed"
                    ? "4. Development and Qualification Planning"
                    : "4. Address gaps and resubmit the trial plan",
                isOwner
                    ? "Submit the qualification package for trial authorization: qualification / trial plan, Control Plan, draft SWI / specification changes and the acceptance criteria."
                    : "Waiting for the project team to submit the qualification package.",
                isOwner && <PlanForm p={p} />,
            );
        case "plan_for_approval":
            return stage.planApproval === "departments"
                ? card(
                      "4. Trial plan & required authorizations",
                      "The approval section below signs the trial plan. When all have approved, the trial is authorized.",
                  )
                : card(
                      "4. Trial plan & required authorizations",
                      "The TRB Admin approves the trial plan.",
                      isAdmin && (
                          <Step
                              p={p}
                              action="plan"
                              yes="Approve trial plan"
                              no="Return: address gaps"
                          />
                      ),
                  );
        case "trial_authorized":
            return card(
                "5. Authorized Trial and Validation",
                (isOwner
                    ? "Run the approved plan with Engineering, Manufacturing and QA, then upload the TRB Report with the results."
                    : "The initiator runs the trial and uploads the TRB Report.") +
                    (p.uses_customer_product
                        ? " The trial uses customer product: the customer trial authorization must be attached first."
                        : ""),
            );
        case "for_approval":
            return card(
                "6. TRB Results Review and TSPI Approval",
                "The approval section below signs the results.",
            );
        case "returned":
            return card(
                "6. Address findings and repeat work",
                isOwner
                    ? "Address the findings, then upload the updated TRB Report."
                    : "Waiting for the originator to address the findings.",
            );
        case "approved":
            return card(
                "Validated: is customer approval required?",
                "The TRB Admin decides whether the customer (ADI) must approve the change.",
                isAdmin && (
                    <Step
                        p={p}
                        action="customer"
                        yes="Yes: ADI review"
                        no="No: document release"
                        noNeedsRemarks={false}
                    />
                ),
            );
        case "adi_review":
            return card(
                "7. ADI Review and applicable 4MCN approval",
                `Notify the ADI Oversight Team and present the TRB. Customer approval and signatures secured?${p.requires_4mcn && !p.four_mcn ? ' On "Yes", the 4MCN is generated automatically from this TEB.' : ""}`,
                isAdmin && (
                    <>
                        <AdiNotice p={p} emails={stage.emails ?? []} />
                        <h3 style={{ margin: "16px 0 8px" }}>ADI decision</h3>
                        <Step
                            p={p}
                            action="secured"
                            yes="Yes: secured"
                            no="No: hold for customer findings"
                            fileType="customer_approval"
                            fileLabel="Customer approval record / signed 4MCN"
                            fields={[
                                ["conditions", "Approval conditions (if any)"],
                            ]}
                            extra={{
                                action: "material_change",
                                label: "Material change: return to TEB",
                            }}
                        />
                    </>
                ),
            );
        case "customer_hold":
            return card(
                "7. Hold and resolve customer findings",
                "When the customer findings are resolved, the TRB goes back to ADI review. A material change goes back to the TRB for reassessment.",
                isAdmin && (
                    <Step
                        p={p}
                        action="resolved"
                        yes="Findings resolved"
                        extra={{
                            action: "material_change",
                            label: "Material change: return to TEB",
                        }}
                    />
                ),
            );
        case "doc_release":
            return card(
                "8. Document Release and Implementation Readiness (Gate 2)",
                "Document owners and TSPI approvers finalize the SWI / specifications; Document Control releases them.",
                isAdmin && (
                    <Step
                        p={p}
                        action="readiness"
                        submit="Record readiness"
                        checks={stage.readiness}
                        fileType="release"
                        fileLabel="Released SWI / specification"
                        fields={[
                            [
                                "swi_spec_numbers",
                                "Released SWI / specification numbers",
                            ],
                        ]}
                        hint="All checked moves to Controlled Implementation; anything unchecked is recorded as a readiness gap."
                    />
                ),
            );
        case "readiness_gaps":
            return card(
                "8. Close readiness gaps",
                "When the gaps are closed, record the readiness again.",
                isAdmin && (
                    <Step
                        p={p}
                        action="gaps_closed"
                        yes="Gaps closed"
                        fileType="release"
                        fileLabel="Released SWI / specification"
                    />
                ),
            );
        case "implementation":
            return card(
                "9. Controlled Implementation",
                "Implement the approved change and record its effective date, affected machines / lots and initial results.",
                (isAdmin || isOwner) && (
                    <Step
                        p={p}
                        action="implemented"
                        submit="Record implementation"
                        fields={[
                            ["effective_date", "Effective date", "date"],
                            [
                                "affected_machines_lots",
                                "Affected machines / lots",
                            ],
                            ["initial_results", "Initial results"],
                        ]}
                    />
                ),
            );
        case "effectiveness":
            return card(
                "10. Effectiveness Verification and TRB Closure Review",
                "Engineering and QA verify the PIEV criteria; the TRB reviews the evidence and approves closure.",
                isAdmin && (
                    <Step
                        p={p}
                        action="effectiveness"
                        yes="Criteria met: close project"
                        no="Not met: contain issues"
                        checks={stage.piev}
                        fields={[
                            ["verified_by", "Verified by (Engineering / QA)"],
                        ]}
                        fileType="closure_report"
                        fileLabel="Effectiveness and Closure Report"
                    />
                ),
            );
        case "containment":
            return card(
                "10. Contain issues and corrective actions",
                isOwner
                    ? "Contain the issues, then upload the updated TRB Report for a new results review (step 6)."
                    : "The originator contains the issues and resubmits the results (step 6).",
            );
        case "closed":
            return card("Project Closed", `Closed on ${p.closed_at ?? "—"}.`);
        case "dropped":
            return card(
                "Closed (Do not proceed)",
                "The proposal was closed at the feasibility review.",
            );
        default:
            return null;
    }
}

// TFP16-008 step 6: email the ADI Oversight Team about the TRB presentation. Addresses are picked from the
// email accounts list or typed in.
function AdiNotice({ p, emails }) {
    const tebUrl = useTebUrl();
    const [picked, setPicked] = useState([]);
    const [typed, setTyped] = useState("");
    const { data, setData, post, processing, errors, transform } = useForm({
        action: "notify_adi",
        remarks: "",
        fields: { presentation_at: "", location: "" },
    });
    const sent = p.gates
        .find((g) => g.gate === "customer")
        ?.rows.find(([l]) => l === "ADI Oversight Team notified")?.[1];
    const submit = (e) => {
        e.preventDefault();
        const all = [...picked, ...typed.split(/[,;\s]+/)]
            .map((x) => x.trim())
            .filter(Boolean);
        transform((d) => ({ ...d, fields: { ...d.fields, emails: all } }));
        post(tebUrl.stage(p.id), {
            preserveScroll: true,
            onSuccess: () => {
                setPicked([]);
                setTyped("");
            },
        });
    };

    return (
        <form className="teb-grid" style={{ maxWidth: 640 }} onSubmit={submit}>
            <h3 style={{ margin: "4px 0 0" }}>Notify ADI Oversight Team</h3>
            {sent && (
                <div className="teb-small teb-muted">Last notified: {sent}</div>
            )}
            {emails.length > 0 && (
                <>
                    <span className="teb-small teb-muted">
                        Pick from the email accounts list (Ctrl+click for
                        several), or type addresses below.
                    </span>
                    <select
                        multiple
                        value={picked}
                        onChange={(e) =>
                            setPicked(
                                Array.from(e.target.selectedOptions).map(
                                    (o) => o.value,
                                ),
                            )
                        }
                        style={{ minHeight: 96 }}
                    >
                        {emails.map((e) => (
                            <option key={e} value={e}>
                                {e}
                            </option>
                        ))}
                    </select>
                </>
            )}
            <input
                type="text"
                value={typed}
                onChange={(e) => setTyped(e.target.value)}
                placeholder="Other email addresses (comma-separated)"
            />
            <div className="teb-row">
                <input
                    type="datetime-local"
                    value={data.fields.presentation_at}
                    onChange={(e) =>
                        setData("fields", {
                            ...data.fields,
                            presentation_at: e.target.value,
                        })
                    }
                    style={{ maxWidth: 230 }}
                />
                <input
                    type="text"
                    value={data.fields.location}
                    onChange={(e) =>
                        setData("fields", {
                            ...data.fields,
                            location: e.target.value,
                        })
                    }
                    placeholder="Location / Teams link"
                    style={{ flex: 1 }}
                />
            </div>
            <textarea
                value={data.remarks}
                onChange={(e) => setData("remarks", e.target.value)}
                placeholder="Notes for ADI (optional)"
                style={{ minHeight: 50 }}
            />
            {Object.entries(errors).map(([k, v]) => (
                <div key={k} className="teb-err">
                    {v}
                </div>
            ))}
            <div>
                <button className="teb-btn" disabled={processing}>
                    Send ADI notice
                </button>
            </div>
        </form>
    );
}

function PlanForm({ p }) {
    const tebUrl = useTebUrl();
    const plan = p.gates.find((g) => g.gate === "plan");
    const { data, setData, post, processing, errors, progress } = useForm({
        trial_plan: null,
        control_plan: null,
        draft_swi: null,
        acceptance_criteria:
            plan?.rows.find(([l]) => l === "Acceptance criteria")?.[1] ?? "",
        uses_customer_product: p.uses_customer_product ? "1" : "0",
        remarks: "",
    });
    const onFile = (type) =>
        p.attachments
            .filter((f) => f.type === type)
            .map((f) => f.original_name)
            .join(", ");
    const file = (key, label, required) => (
        <label className="teb-grid" style={{ gap: 4 }}>
            <span className="teb-l">
                {label}
                {required ? " *" : ""}
            </span>
            <input
                type="file"
                onChange={(e) => setData(key, e.target.files[0])}
            />
            {onFile(key) && (
                <span className="teb-small teb-muted">
                    On file: {onFile(key)}
                </span>
            )}
            {errors[key] && <span className="teb-err">{errors[key]}</span>}
        </label>
    );

    return (
        <form
            className="teb-grid"
            style={{ maxWidth: 640 }}
            onSubmit={(e) => {
                e.preventDefault();
                post(tebUrl.plan(p.id), {
                    forceFormData: true,
                    preserveScroll: true,
                });
            }}
        >
            {file(
                "trial_plan",
                "Qualification / trial plan",
                !onFile("trial_plan"),
            )}
            {file("control_plan", "FMEA / Control Plan")}
            {file("draft_swi", "Draft SWI / specification changes")}
            <label className="teb-grid" style={{ gap: 4 }}>
                <span className="teb-l">Acceptance criteria *</span>
                <textarea
                    value={data.acceptance_criteria}
                    onChange={(e) =>
                        setData("acceptance_criteria", e.target.value)
                    }
                    style={{ minHeight: 60 }}
                />
                {errors.acceptance_criteria && (
                    <span className="teb-err">
                        {errors.acceptance_criteria}
                    </span>
                )}
            </label>
            <div className="teb-checks">
                <span className="teb-l">
                    Does the trial use customer product?
                </span>
                <label>
                    <input
                        type="radio"
                        checked={data.uses_customer_product === "1"}
                        onChange={() => setData("uses_customer_product", "1")}
                    />{" "}
                    Yes (customer trial authorization needed before the results)
                </label>
                <label>
                    <input
                        type="radio"
                        checked={data.uses_customer_product === "0"}
                        onChange={() => setData("uses_customer_product", "0")}
                    />{" "}
                    No
                </label>
            </div>
            <input
                type="text"
                value={data.remarks}
                onChange={(e) => setData("remarks", e.target.value)}
                placeholder="Remarks (optional)"
            />
            {["status", "approval"].map(
                (k) =>
                    errors[k] && (
                        <div key={k} className="teb-err">
                            {errors[k]}
                        </div>
                    ),
            )}
            <div>
                <button className="teb-btn" disabled={processing}>
                    {processing
                        ? `Submitting… ${progress?.percentage ?? ""}%`
                        : "Submit for trial authorization"}
                </button>
            </div>
        </form>
    );
}

// One step's form: optional checklist, text fields, supporting file and remarks, then Yes / No
// (or a single submit), plus an optional extra action such as "Material change: return to TEB".
function Step({
    p,
    action,
    yes,
    no,
    submit,
    checks,
    fields = [],
    fileType,
    fileLabel,
    hint,
    extra,
    noNeedsRemarks = true,
}) {
    const tebUrl = useTebUrl();
    const [key, setKey] = useState(0);
    const { data, setData, post, processing, errors, transform } = useForm({
        action,
        decision: 1,
        remarks: "",
        stage_file: null,
        stage_file_type: fileType ?? "other",
        checks: Object.fromEntries(
            Object.keys(checks ?? {}).map((k) => [k, false]),
        ),
        fields: Object.fromEntries(fields.map(([k]) => [k, ""])),
    });
    const send = (decision, as = action) => {
        transform((d) => ({
            ...d,
            action: as,
            decision: decision ? 1 : 0,
            checks: Object.fromEntries(
                Object.entries(d.checks).map(([k, v]) => [k, v ? 1 : 0]),
            ),
        }));
        post(tebUrl.stage(p.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setData("remarks", "");
                setKey(key + 1);
            },
        });
    };

    return (
        <div className="teb-grid" style={{ maxWidth: 640 }}>
            {checks &&
                Object.entries(checks).map(([k, label]) => (
                    <label
                        key={k}
                        className="teb-row"
                        style={{ gap: 8, alignItems: "flex-start" }}
                    >
                        <input
                            type="checkbox"
                            checked={data.checks[k]}
                            onChange={(e) =>
                                setData("checks", {
                                    ...data.checks,
                                    [k]: e.target.checked,
                                })
                            }
                            style={{ marginTop: 3 }}
                        />
                        <span>{label}</span>
                    </label>
                ))}
            {fields.map(([k, label, type]) => (
                <label key={k} className="teb-grid" style={{ gap: 4 }}>
                    <span className="teb-l">{label}</span>
                    {type === "date" ? (
                        <input
                            type="date"
                            value={data.fields[k]}
                            onChange={(e) =>
                                setData("fields", {
                                    ...data.fields,
                                    [k]: e.target.value,
                                })
                            }
                            style={{ maxWidth: 200 }}
                        />
                    ) : (
                        <input
                            type="text"
                            value={data.fields[k]}
                            onChange={(e) =>
                                setData("fields", {
                                    ...data.fields,
                                    [k]: e.target.value,
                                })
                            }
                        />
                    )}
                </label>
            ))}
            {fileType && (
                <label className="teb-grid" style={{ gap: 4 }}>
                    <span className="teb-l">
                        {fileLabel ?? "Supporting file"}
                    </span>
                    <input
                        key={key}
                        type="file"
                        onChange={(e) =>
                            setData("stage_file", e.target.files[0])
                        }
                    />
                </label>
            )}
            <textarea
                value={data.remarks}
                onChange={(e) => setData("remarks", e.target.value)}
                style={{ minHeight: 50 }}
                placeholder={
                    no && noNeedsRemarks
                        ? "Remarks (required for No)"
                        : extra
                          ? "Remarks (required for a material change)"
                          : "Remarks (optional)"
                }
            />
            {Object.values(errors).map((e) => (
                <div key={e} className="teb-err">
                    {e}
                </div>
            ))}
            {hint && <div className="teb-small teb-muted">{hint}</div>}
            <div className="teb-row">
                {submit ? (
                    <button
                        type="button"
                        className="teb-btn"
                        disabled={processing}
                        onClick={() => send(true)}
                    >
                        {submit}
                    </button>
                ) : (
                    <button
                        type="button"
                        className="teb-btn ok"
                        disabled={processing}
                        onClick={() => send(true)}
                    >
                        {yes}
                    </button>
                )}
                {no && (
                    <button
                        type="button"
                        className="teb-btn bad"
                        disabled={processing}
                        onClick={() => send(false)}
                    >
                        {no}
                    </button>
                )}
                {extra && (
                    <button
                        type="button"
                        className="teb-btn ghost"
                        disabled={processing}
                        onClick={() => send(true, extra.action)}
                    >
                        {extra.label}
                    </button>
                )}
            </div>
        </div>
    );
}
