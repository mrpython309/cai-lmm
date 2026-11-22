import { api } from './workspace.js';

const root = document.getElementById('orgList');
const apiUrl = root.dataset.apiUrl;
const table = document.getElementById('orgTable');
const status = document.getElementById('listStatus');
const error = document.getElementById('listError');
const success = document.getElementById('listSuccess');
const pagination = document.getElementById('listPagination');
const previous = document.getElementById('previousPage');
const next = document.getElementById('nextPage');
const statusFilter = document.getElementById('statusFilter');

const orgDialog = document.getElementById('orgDialog');
const closeOrgDialog = document.getElementById('closeOrgDialog');
const cancelOrgForm = document.getElementById('cancelOrgForm');
const orgForm = document.getElementById('orgForm');
const orgFormError = document.getElementById('orgFormError');
const saveOrgBtn = document.getElementById('saveOrgBtn');

const deleteDialog = document.getElementById('deleteOrgDialog');
const deleteDescription = document.getElementById('deleteOrgDescription');
const deleteError = document.getElementById('deleteOrgError');
const cancelDelete = document.getElementById('cancelDeleteOrg');
const confirmDelete = document.getElementById('confirmDeleteOrg');

let page = 1;
let loading = false;
let editingId = null;
let deleteTarget = null;

function actionBtn(label, style = 'secondary') {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = `btn btn-${style}`;
    btn.textContent = label;
    return btn;
}

function showSuccess(message) {
    success.textContent = message;
    success.hidden = false;
    success.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    setTimeout(() => { success.hidden = true; }, 5000);
}

function clearValidationErrors() {
    orgFormError.hidden = true;
    orgFormError.textContent = '';
    ['organization_code', 'organization_name', 'organization_type'].forEach(name => {
        const err = document.getElementById(`error_${name}`);
        if (err) {
            err.hidden = true;
            err.textContent = '';
        }
        const field = orgForm.elements[name];
        if (field) field.removeAttribute('aria-invalid');
    });
}

function displayValidationErrors(errors = {}) {
    let first = null;
    for (const [name, messages] of Object.entries(errors)) {
        const errEl = document.getElementById(`error_${name}`);
        if (errEl) {
            errEl.textContent = messages[0];
            errEl.hidden = false;
        }
        const field = orgForm.elements[name];
        if (field) {
            field.setAttribute('aria-invalid', 'true');
            if (!first) first = field;
        }
    }
    if (first) first.focus();
}

function renderRow(org) {
    const row = table.tBodies[0].insertRow();
    const cells = [
        org.organization_code,
        org.organization_name,
        org.organization_type ?? '—',
        org.branches_count ?? 0,
    ];

    cells.forEach((val, idx) => {
        const cell = row.insertCell();
        cell.dataset.label = table.tHead.rows[0].cells[idx].textContent;
        cell.textContent = val;
    });

    // Status badge
    const statusCell = row.insertCell();
    statusCell.dataset.label = 'Status';
    const badge = document.createElement('span');
    badge.className = `badge ${org.is_active ? 'badge-active' : 'badge-inactive'}`;
    badge.textContent = org.is_active ? 'Active' : 'Inactive';
    statusCell.append(badge);

    // Actions
    const actionCell = row.insertCell();
    actionCell.dataset.label = 'Actions';
    const buttons = document.createElement('div');
    buttons.className = 'actions';

    const editBtn = actionBtn('Edit');
    editBtn.addEventListener('click', () => openEdit(org));

    const delBtn = actionBtn('Delete', 'danger');
    delBtn.addEventListener('click', () => openDelete(org));

    buttons.append(editBtn, delBtn);
    actionCell.append(buttons);
}

async function loadList(targetPage = page) {
    if (loading) return;
    loading = true;
    status.textContent = 'Loading organizations…';
    status.hidden = false;
    error.hidden = true;
    table.hidden = true;
    pagination.hidden = true;

    const url = new URL(apiUrl, window.location.origin);
    url.searchParams.set('page', targetPage);
    if (statusFilter.value !== '') {
        url.searchParams.set('is_active', statusFilter.value);
    }

    try {
        const result = await api(url);
        const meta = result.meta;
        page = meta.current_page;
        table.tBodies[0].replaceChildren();
        result.data.forEach(renderRow);

        document.getElementById('listCount').textContent = `${meta.total} ${meta.total === 1 ? 'organization' : 'organizations'}`;
        table.hidden = result.data.length === 0;
        status.textContent = 'No organizations found for the selected filter.';
        status.hidden = result.data.length > 0;
        pagination.hidden = meta.last_page <= 1;
        document.getElementById('currentPage').textContent = `Page ${page} of ${meta.last_page}`;
        previous.disabled = page <= 1;
        next.disabled = page >= meta.last_page;
    } catch (err) {
        error.hidden = false;
        document.getElementById('listErrorText').textContent = err.message;
        status.hidden = true;
    } finally {
        loading = false;
    }
}

function openCreate() {
    editingId = null;
    clearValidationErrors();
    orgForm.reset();
    orgForm.elements.is_active.checked = true;
    document.getElementById('orgDialogTitle').textContent = 'New organization';
    saveOrgBtn.textContent = 'Save organization';
    orgDialog.showModal();
}

function openEdit(org) {
    editingId = org.id;
    clearValidationErrors();
    orgForm.elements.organization_code.value = org.organization_code;
    orgForm.elements.organization_name.value = org.organization_name;
    orgForm.elements.organization_type.value = org.organization_type ?? '';
    orgForm.elements.is_active.checked = Boolean(org.is_active);
    document.getElementById('orgDialogTitle').textContent = `Edit organization (${org.organization_code})`;
    saveOrgBtn.textContent = 'Update organization';
    orgDialog.showModal();
}

async function handleSave(e) {
    e.preventDefault();
    clearValidationErrors();
    saveOrgBtn.disabled = true;

    const payload = {
        organization_code: orgForm.elements.organization_code.value.trim(),
        organization_name: orgForm.elements.organization_name.value.trim(),
        organization_type: orgForm.elements.organization_type.value.trim() || null,
        is_active: orgForm.elements.is_active.checked,
    };

    const targetUrl = editingId ? `${apiUrl}/${editingId}` : apiUrl;
    const method = editingId ? 'PUT' : 'POST';

    try {
        await api(targetUrl, { method, data: payload });
        orgDialog.close();
        showSuccess(editingId ? 'Organization updated successfully.' : 'Organization created successfully.');
        await loadList(editingId ? page : 1);
    } catch (err) {
        if (err.status === 422 && err.fields) {
            displayValidationErrors(err.fields);
            orgFormError.textContent = 'Please review the highlighted validation errors below.';
        } else {
            orgFormError.textContent = err.message;
        }
        orgFormError.hidden = false;
    } finally {
        saveOrgBtn.disabled = false;
    }
}

function openDelete(org) {
    deleteTarget = org;
    deleteError.hidden = true;
    deleteError.textContent = '';
    deleteDescription.textContent = `Are you sure you want to delete "${org.organization_name}" (${org.organization_code})? This action cannot be undone.`;
    deleteDialog.showModal();
}

async function handleDelete() {
    if (!deleteTarget) return;
    confirmDelete.disabled = true;
    deleteError.hidden = true;

    try {
        await api(`${apiUrl}/${deleteTarget.id}`, { method: 'DELETE' });
        deleteDialog.close();
        showSuccess(`Organization "${deleteTarget.organization_name}" was deleted.`);
        deleteTarget = null;
        await loadList(page);
    } catch (err) {
        deleteError.textContent = err.message;
        deleteError.hidden = false;
    } finally {
        confirmDelete.disabled = false;
    }
}

// Event Listeners
document.getElementById('openCreateBtn').addEventListener('click', openCreate);
closeOrgDialog.addEventListener('click', () => orgDialog.close());
cancelOrgForm.addEventListener('click', () => orgDialog.close());
orgForm.addEventListener('submit', handleSave);

cancelDelete.addEventListener('click', () => deleteDialog.close());
confirmDelete.addEventListener('click', handleDelete);

statusFilter.addEventListener('change', () => loadList(1));
previous.addEventListener('click', () => loadList(page - 1));
next.addEventListener('click', () => loadList(page + 1));
document.getElementById('retryList').addEventListener('click', () => loadList(page));

// Initial load
loadList(1);
