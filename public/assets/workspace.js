const csrf = document.querySelector('meta[name="csrf-token"]').content;
const timeZone = document.querySelector('meta[name="workspace-timezone"]').content;

export const leadLabels = {
    organization: 'Dealer organization',
    branch: 'Branch location',
    lead_source: 'Lead source',
    business_type: 'Business type',
    company_name: 'Company name',
    location: 'Company / customer location',
    customer_name: 'Customer name',
    mobile: 'Mobile number',
    email: 'Email address',
    application: 'Vehicle application',
    vehicle_category: 'Vehicle category',
    hotspot: 'Hotspot',
    current_vehicle: 'Current vehicle',
    exchange: 'Exchange interested?',
    recommended_model: 'Recommended Mahindra model',
    test_drive: 'Test drive required?',
    finance: 'Finance required?',
    priority: 'Purchase timeline',
    follow_up_date: 'Next follow-up date',
    remarks: 'Remarks / customer requirement',
};

export async function api(url, { method = 'GET', data, body, signal } = {}) {
    const headers = { Accept: 'application/json' };
    if (method !== 'GET') headers['X-CSRF-TOKEN'] = csrf;

    let payloadBody;
    if (data !== undefined) {
        headers['Content-Type'] = 'application/json';
        payloadBody = JSON.stringify(data);
    } else if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
        payloadBody = typeof body === 'string' ? body : JSON.stringify(body);
    }

    let response;
    try {
        response = await fetch(url, { method, headers, body: payloadBody, signal, cache: 'no-store' });
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
    const error = new Error(payload?.message ?? messages[response.status] ?? 'The workspace could not complete the request. Try again.');
    error.status = response.status;
    error.fields = response.status === 422 ? payload?.errors : undefined;
    error.errors = error.fields;
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
