const csrf = document.querySelector('meta[name="csrf-token"]').content;
const timeZone = document.querySelector('meta[name="workspace-timezone"]').content;

export const leadLabels = {
    enquiry_type: 'Enquiry type',
    lead_source: 'Enquiry source',
    lead_source_channel: 'Lead source channel',
    assigned_employee_display: 'Assigned consultant',
    business_type: 'Business type',
    branch_name: 'Branch / Location',
    location: 'Company / customer location',
    company_name: 'Company name',
    customer_name: 'Customer name',
    mobile: 'Mobile number',
    email: 'Email address',
    application: 'Vehicle application',
    vehicle_category: 'Vehicle category',
    hotspot: 'Hotspot',
    current_vehicle: 'Current vehicle',
    exchange: 'Exchange interested?',
    recommended_model: 'Recommended Mahindra model',
    variant_name: 'Variant',
    test_drive: 'Test drive required?',
    finance: 'Finance required?',
    priority: 'Purchase timeline',
    lead_status: 'Lead status',
    status_response: 'Status response',
    follow_up_date: 'Next follow-up date',
    remarks: 'Remarks / customer requirement',
};

export async function api(url, { method = 'GET', data, signal } = {}) {
    const headers = { Accept: 'application/json' };
    if (method !== 'GET') headers['X-CSRF-TOKEN'] = csrf;
    if (data !== undefined) headers['Content-Type'] = 'application/json';

    let response;
    try {
        response = await fetch(url, { method, headers, body: JSON.stringify(data), signal, cache: 'no-store' });
    } catch (error) {
        if (error.name === 'AbortError') throw error;
        throw new Error('The workspace could not reach the server. Check your connection and try again.');
    }

    if (response.status === 204) return null;
    const payload = await response.json().catch(() => null);
    if (response.ok && payload) return payload;

    const messages = {
        401: 'Your session has expired. Reload the page to sign in again.',
        404: 'This record could not be found. Refresh the registered leads list.',
        419: 'Your session has expired. Reload the page to sign in again.',
        422: 'Check the highlighted fields and try again.',
        429: 'Too many requests. Wait a moment and try again.',
    };
    const error = new Error(messages[response.status] ?? 'The workspace could not complete the request. Try again.');
    error.status = response.status;
    error.fields = response.status === 422 ? payload?.errors : undefined;
    throw error;
}

export function workspaceToday() {
    const parts = new Intl.DateTimeFormat('en-IN', {
        timeZone, year: 'numeric', month: '2-digit', day: '2-digit',
    }).formatToParts();
    const date = Object.fromEntries(parts.map(({ type, value }) => [type, value]));

    return `${date.year}-${date.month}-${date.day}`;
}

export function formatDate(value) {
    return new Intl.DateTimeFormat('en-IN', {
        timeZone: 'UTC', day: '2-digit', month: 'short', year: 'numeric',
    }).format(new Date(`${value}T00:00:00Z`));
}
