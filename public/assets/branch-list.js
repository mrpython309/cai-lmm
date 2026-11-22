import { api } from './workspace.js';

const root = document.getElementById('branchList');
const apiUrl = root.dataset.apiUrl;
const orgsUrl = root.dataset.orgsUrl;

const table = document.getElementById('branchTable');
const status = document.getElementById('listStatus');
const error = document.getElementById('listError');
const success = document.getElementById('listSuccess');
const pagination = document.getElementById('listPagination');
const previous = document.getElementById('previousPage');
const next = document.getElementById('nextPage');

// Cascading Filter Elements
const filterOrgSelect = document.getElementById('filterOrgSelect');
const cascadingBranchPicker = document.getElementById('cascadingBranchPicker');
const resetFilterBtn = document.getElementById('resetFilterBtn');

// Modal Elements
const branchDialog = document.getElementById('branchDialog');
const closeBranchDialog = document.getElementById('closeBranchDialog');
const cancelBranchForm = document.getElementById('cancelBranchForm');
const branchForm = document.getElementById('branchForm');
const branchFormError = document.getElementById('branchFormError');
const saveBranchBtn = document.getElementById('saveBranchBtn');
const branchOrgSelect = document.getElementById('branchOrg');

// Delete Elements
const deleteDialog = document.getElementById('deleteBranchDialog');
const deleteDescription = document.getElementById('deleteBranchDescription');
const deleteError = document.getElementById('deleteBranchError');
const cancelDelete = document.getElementById('cancelDeleteBranch');
const confirmDelete = document.getElementById('confirmDeleteBranch');

let page = 1;
let loading = false;
let editingId = null;
let deleteTarget = null;
let activeOrganizations = [];

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
    branchFormError.hidden = true;
    branchFormError.textContent = '';
    ['organization_id', 'branch_code', 'branch_name', 'address', 'city', 'state', 'pincode', 'contact_person', 'contact_number', 'email'].forEach(name => {
        const err = document.getElementById(`error_${name}`);
        if (err) {
            err.hidden = true;
            err.textContent = '';
        }
        const field = branchForm.elements[name];
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
        const field = branchForm.elements[name];
        if (field) {
            field.setAttribute('aria-invalid', 'true');
            if (!first) first = field;
        }
    }
    if (first) first.focus();
}

/**
 * Loads active organizations from API to populate selection dropdowns
 */
async function loadActiveOrganizations() {
    try {
        const res = await api(`${orgsUrl}?active=1&all=1`);
        activeOrganizations = res.data ?? [];

        // 1. Populate Filter Dropdown
        filterOrgSelect.replaceChildren(
            new Option('All organizations', ''),
            ...activeOrganizations.map(org => new Option(`${org.organization_name} (${org.organization_code})`, org.id))
        );

        // 2. Populate Form Modal Dropdown
        populateFormOrgDropdown();
    } catch (err) {
        console.error('Failed to load active organizations:', err);
    }
}

function populateFormOrgDropdown(selectedOrg = null) {
    branchOrgSelect.replaceChildren(new Option('Select organization (Active only)', ''));
    let orgOptions = [...activeOrganizations];

    // If editing a branch whose parent org is inactive, include it as an option
    if (selectedOrg && !orgOptions.some(o => o.id === selectedOrg.id)) {
        orgOptions.push({
            id: selectedOrg.id,
            organization_name: `${selectedOrg.organization_name} (Inactive)`,
            organization_code: selectedOrg.organization_code,
        });
    }

    orgOptions.forEach(org => {
        const opt = new Option(`${org.organization_name} (${org.organization_code})`, org.id);
        branchOrgSelect.append(opt);
    });
}

/**
 * Updates the cascading branches picker when an organization is selected
 */
async function handleFilterOrgChange() {
    const orgId = filterOrgSelect.value;
    cascadingBranchPicker.replaceChildren();

    if (!orgId) {
        cascadingBranchPicker.disabled = true;
        cascadingBranchPicker.append(new Option('Select organization first', ''));
    } else {
        cascadingBranchPicker.disabled = true;
        cascadingBranchPicker.append(new Option('Loading branches…', ''));
        try {
            // Fetch only active branches under the selected organization
            const res = await api(`${apiUrl}?organization_id=${orgId}&active=1&all=1`);
            const branches = res.data ?? [];
            cascadingBranchPicker.replaceChildren(new Option(`All active branches in organization (${branches.length})`, ''));
            branches.forEach(b => {
                cascadingBranchPicker.append(new Option(`${b.branch_name} (${b.branch_code})`, b.id));
            });
            cascadingBranchPicker.disabled = false;
        } catch (err) {
            cascadingBranchPicker.replaceChildren(new Option('Could not load branches', ''));
        }
    }

    await loadList(1);
}

function renderRow(branch) {
    const row = table.tBodies[0].insertRow();
    const cityState = [branch.city, branch.state].filter(Boolean).join(', ') || '—';
    const contact = [branch.contact_person, branch.contact_number].filter(Boolean).join(' • ') || '—';
    const orgDisplay = branch.organization ? `${branch.organization.organization_name} (${branch.organization.organization_code})` : '—';

    const cells = [
        branch.branch_code,
        branch.branch_name,
        orgDisplay,
        cityState,
        contact,
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
    badge.className = `badge ${branch.is_active ? 'badge-active' : 'badge-inactive'}`;
    badge.textContent = branch.is_active ? 'Active' : 'Inactive';
    statusCell.append(badge);

    // Actions
    const actionCell = row.insertCell();
    actionCell.dataset.label = 'Actions';
    const buttons = document.createElement('div');
    buttons.className = 'actions';

    const editBtn = actionBtn('Edit');
    editBtn.addEventListener('click', () => openEdit(branch));

    const delBtn = actionBtn('Delete', 'danger');
    delBtn.addEventListener('click', () => openDelete(branch));

    buttons.append(editBtn, delBtn);
    actionCell.append(buttons);
}

async function loadList(targetPage = page) {
    if (loading) return;
    loading = true;
    status.textContent = 'Loading branches…';
    status.hidden = false;
    error.hidden = true;
    table.hidden = true;
    pagination.hidden = true;

    const url = new URL(apiUrl, window.location.origin);
    url.searchParams.set('page', targetPage);

    const selectedOrgId = filterOrgSelect.value;
    if (selectedOrgId) {
        url.searchParams.set('organization_id', selectedOrgId);
    }

    try {
        const result = await api(url);
        const meta = result.meta;
        page = meta.current_page;
        table.tBodies[0].replaceChildren();
        result.data.forEach(renderRow);

        document.getElementById('listCount').textContent = `${meta.total} ${meta.total === 1 ? 'branch' : 'branches'}`;
        table.hidden = result.data.length === 0;
        status.textContent = 'No branches found for the selected organization.';
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
    branchForm.reset();
    populateFormOrgDropdown();
    if (filterOrgSelect.value) {
        branchOrgSelect.value = filterOrgSelect.value;
    }
    branchForm.elements.is_active.checked = true;
    document.getElementById('branchDialogTitle').textContent = 'New branch';
    saveBranchBtn.textContent = 'Save branch';
    branchDialog.showModal();
}

function openEdit(branch) {
    editingId = branch.id;
    clearValidationErrors();
    populateFormOrgDropdown(branch.organization);
    branchOrgSelect.value = branch.organization_id;
    branchForm.elements.branch_code.value = branch.branch_code;
    branchForm.elements.branch_name.value = branch.branch_name;
    branchForm.elements.address.value = branch.address ?? '';
    branchForm.elements.city.value = branch.city ?? '';
    branchForm.elements.state.value = branch.state ?? '';
    branchForm.elements.pincode.value = branch.pincode ?? '';
    branchForm.elements.contact_person.value = branch.contact_person ?? '';
    branchForm.elements.contact_number.value = branch.contact_number ?? '';
    branchForm.elements.email.value = branch.email ?? '';
    branchForm.elements.is_active.checked = Boolean(branch.is_active);

    document.getElementById('branchDialogTitle').textContent = `Edit branch (${branch.branch_code})`;
    saveBranchBtn.textContent = 'Update branch';
    branchDialog.showModal();
}

async function handleSave(e) {
    e.preventDefault();
    clearValidationErrors();
    saveBranchBtn.disabled = true;

    const payload = {
        organization_id: branchOrgSelect.value ? parseInt(branchOrgSelect.value, 10) : null,
        branch_code: branchForm.elements.branch_code.value.trim(),
        branch_name: branchForm.elements.branch_name.value.trim(),
        address: branchForm.elements.address.value.trim() || null,
        city: branchForm.elements.city.value.trim() || null,
        state: branchForm.elements.state.value.trim() || null,
        pincode: branchForm.elements.pincode.value.trim() || null,
        contact_person: branchForm.elements.contact_person.value.trim() || null,
        contact_number: branchForm.elements.contact_number.value.trim() || null,
        email: branchForm.elements.email.value.trim() || null,
        is_active: branchForm.elements.is_active.checked,
    };

    const targetUrl = editingId ? `${apiUrl}/${editingId}` : apiUrl;
    const method = editingId ? 'PUT' : 'POST';

    try {
        await api(targetUrl, { method, data: payload });
        branchDialog.close();
        showSuccess(editingId ? 'Branch updated successfully.' : 'Branch created successfully.');
        await loadList(editingId ? page : 1);
        if (filterOrgSelect.value) {
            await handleFilterOrgChange();
        }
    } catch (err) {
        if (err.status === 422 && err.fields) {
            displayValidationErrors(err.fields);
            branchFormError.textContent = 'Please review the highlighted validation errors below.';
        } else {
            branchFormError.textContent = err.message;
        }
        branchFormError.hidden = false;
    } finally {
        saveBranchBtn.disabled = false;
    }
}

function openDelete(branch) {
    deleteTarget = branch;
    deleteError.hidden = true;
    deleteError.textContent = '';
    deleteDescription.textContent = `Are you sure you want to delete branch "${branch.branch_name}" (${branch.branch_code})?`;
    deleteDialog.showModal();
}

async function handleDelete() {
    if (!deleteTarget) return;
    confirmDelete.disabled = true;
    deleteError.hidden = true;

    try {
        await api(`${apiUrl}/${deleteTarget.id}`, { method: 'DELETE' });
        deleteDialog.close();
        showSuccess(`Branch "${deleteTarget.branch_name}" was deleted.`);
        deleteTarget = null;
        await loadList(page);
    } catch (err) {
        deleteError.textContent = err.message;
        deleteError.hidden = false;
    } finally {
        confirmDelete.disabled = false;
    }
}

function resetFilters() {
    filterOrgSelect.value = '';
    cascadingBranchPicker.replaceChildren(new Option('Select organization first', ''));
    cascadingBranchPicker.disabled = true;
    loadList(1);
}

// Event Listeners
document.getElementById('openCreateBtn').addEventListener('click', openCreate);
closeBranchDialog.addEventListener('click', () => branchDialog.close());
cancelBranchForm.addEventListener('click', () => branchDialog.close());
branchForm.addEventListener('submit', handleSave);

cancelDelete.addEventListener('click', () => deleteDialog.close());
confirmDelete.addEventListener('click', handleDelete);

filterOrgSelect.addEventListener('change', handleFilterOrgChange);
resetFilterBtn.addEventListener('click', resetFilters);

previous.addEventListener('click', () => loadList(page - 1));
next.addEventListener('click', () => loadList(page + 1));
document.getElementById('retryList').addEventListener('click', () => loadList(page));

// Initial bootstrap
(async () => {
    await loadActiveOrganizations();
    await loadList(1);
})();
