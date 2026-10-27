import { usePage } from "@inertiajs/react";

// TEB routes (routes/teb.php), built from the app's /{APP_NAME} prefix. Kept here so the pages do not need Ziggy.
export function useTebUrl() {
    return makeTebUrl(usePage().props.teb?.base ?? "");
}

export const makeTebUrl = (base) => ({
    dashboard: () => base || "/",
    projects: (query) => `${base}/projects${qs(query)}`,
    create: () => `${base}/projects/create`,
    show: (id) => `${base}/projects/${id}`,
    edit: (id) => `${base}/projects/${id}/edit`,
    update: (id) => `${base}/projects/${id}`,
    complete: (id) => `${base}/projects/${id}/complete`,
    form: (id) => `${base}/projects/${id}/form`,
    upload: (id) => `${base}/projects/${id}/attachments`,
    export: (query) => `${base}/projects/export${qs(query)}`,
    fourMcnCreate: (id) => `${base}/projects/${id}/4mcn/create`,
    fourMcnStore: (id) => `${base}/projects/${id}/4mcn`,
    fourMcnShow: (id) => `${base}/projects/${id}/4mcn`,
    approvals: (query) => `${base}/approvals${qs(query)}`,
    act: (approvalId) => `${base}/approvals/${approvalId}`,
    review: (query) => `${base}/review${qs(query)}`,
    schedule: (id) => `${base}/projects/${id}/schedule`,
    resend: (id, reviewId) =>
        `${base}/projects/${id}/reviews/${reviewId}/resend`,
    status: (id) => `${base}/projects/${id}/status`,
    assign: (id) => `${base}/projects/${id}/teb-no`,
    settings: () => `${base}/settings`,
    saveApprover: () => `${base}/settings/approvers`,
});

function qs(query) {
    if (!query) return "";
    const params = new URLSearchParams();
    Object.entries(query).forEach(([k, v]) => {
        if (v !== undefined && v !== null && v !== "" && v !== false)
            params.append(k, v === true ? "1" : v);
    });
    const s = params.toString();
    return s ? `?${s}` : "";
}
