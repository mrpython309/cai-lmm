import { api } from './workspace.js';

const root = document.getElementById('employeeList');
const apiUrl = root.dataset.apiUrl;
const orgsUrl = root.dataset.orgsUrl;
const branchesUrl = root.dataset.branchesUrl;

const table = document.getElementById('employeeTable');
const status = document.getElementById('listStatus');
const error = document.getElementById('listError');
const success = document.getElementById('listSuccess');
const pagination = document.getElementById('listPagination');
const previous = document.getElementById('previousPage');
const next = document.getElementById('nextPage');
const listCount = document.getElementById('listCount');

// Filter Elements
const filterOrgSelect = document.getElementById('filterOrgSelect');
const filterBranchSelect = document.getElementById('filterBranchSelect');
const searchFilter = document.getElementById('searchFilter');
const statusFilter = document.getElementById('statusFilter');
const resetFilterBtn = document.getElementById('resetFilterBtn');
const openCreateBtn = document.getElementById('openCreateBtn');

// Modal Elements
const employeeDialog = document.getElementById('employeeDialog');
const employeeDialogTitle = document.getElementById('employeeDialogTitle');
const closeEmployeeDialog = document.getElementById('closeEmployeeDialog');
const cancelEmployeeForm = document.getElementById('cancelEmployeeForm');
const employeeForm = document.getElementById('employeeForm');
const employeeFormError = document.getElementById('employeeFormError');
const saveEmployeeBtn = document.getElementById('saveEmployeeBtn');
const employeeOrgSelect = document.getElementById('employeeOrg');
const employeeBranchSelect = document.getElementById('employeeBranch');

// Delete Elements
const deleteDialog = document.getElementById('deleteEmployeeDialog');
const deleteDescription = document.getElementById('deleteEmployeeDescription');
const deleteError = document.getElementById('deleteEmployeeError');
const cancelDelete = document.getElementById('cancelDeleteEmployee');
const confirmDelete = document.getElementById('confirmDeleteEmployee');

let page = 1;
let loading = false;
let editingId = null;
let deleteTarget = null;
let activeOrganizations = [];
let searchTimeout = null;

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
    employeeFormError.hidden = true;
    employeeFormError.textContent = '';
    ['organization_id', 'branch_id', 'employee_id', 'name', 'email', 'mobile_number', 'designation'].forEach(name => {
        const err = document.getElementById(`error_${name}`);
        if (err) {
            err.hidden = true;
            err.textContent = '';
        }
        const field = employeeForm.elements[name];
        if (field) field.removeAttribute('aria-invalid');
    });
}

function displayValidationErrors(errors = {}) {
    let first = null;
    employeeFormError.textContent = 'Please correct the highlighted errors below and try again.';
    employeeFormError.hidden = false;

    for (const [name, messages] of Object.entries(errors)) {
        const errEl = document.getElementById(`error_${name}`);
        if (errEl) {
            errEl.textContent = Array.isArray(messages) ? messages[0] : messages;
            errEl.hidden = false;
        }
        const field = employeeForm.elements[name];
        if (field) {
            field.setAttribute('aria-invalid', 'true');
            if (!first) first = field;
        }
    }
    if (first) first.focus();
}
            field.setAttribute('aria-invalid', 'true');
            if (!first) first = field;
        }
    }
    if (first) first.focus();
}

/**
 * Loads active organizations to populate dropdowns
 */
async function loadActiveOrganizations() {
    try {
        const res = await api(`${orgsUrl}?active=1&all=1`);
        activeOrganizations = res.data ?? [];

        filterOrgSelect.replaceChildren(
            new Option('All organizations', ''),
            ...activeOrganizations.map(org => new Option(`${org.organization_name} (${org.organization_code})`, org.id))
        );

        populateFormOrgDropdown();
    } catch (err) {
        console.error('Failed to load active organizations:', err);
    }
}

function populateFormOrgDropdown(selectedOrg = null) {
    employeeOrgSelect.replaceChildren(new Option('Select organization (Active only)', ''));
    let orgOptions = [...activeOrganizations];

    if (selectedOrg && !orgOptions.some(o => o.id === selectedOrg.id)) {
        orgOptions.push({
            id: selectedOrg.id,
            organization_name: `${selectedOrg.organization_name} (Inactive)`,
            organization_code: selectedOrg.organization_code,
        });
    }

    orgOptions.forEach(org => {
        const opt = new Option(`${org.organization_name} (${org.organization_code})`, org.id);
        employeeOrgSelect.append(opt);
    });
}

/**
 * Loads branches for a given organization into a select element
 */
async function loadBranchesForOrg(orgId, targetSelect, defaultPrompt, selectedBranchId = null) {
    targetSelect.replaceChildren();

    if (!orgId) {
        targetSelect.disabled = true;
        targetSelect.append(new Option('Select organization first', ''));
        return;
    }

    targetSelect.disabled = true;
    targetSelect.append(new Option('Loading branches…', ''));

    try {
        const res = await api(`${branchesUrl}?organization_id=${orgId}&active=1&all=1`);
        let branches = res.data ?? [];

        // If editing and branch is currently inactive, retain it
        if (selectedBranchId && !branches.some(b => b.id === selectedBranchId)) {
            try {
                const singleRes = await api(`${branchesUrl}/${selectedBranchId}`);
                if (singleRes.data) {
                    branches.push({
                        ...singleRes.data,
                        branch_name: `${singleRes.data.branch_name} (Inactive)`,
                    });
                }
            } catch (ignore) {}
        }

        targetSelect.replaceChildren(new Option(defaultPrompt, ''));
        branches.forEach(b => {
            const opt = new Option(`${b.branch_name} (${b.branch_code})`, b.id);
            if (selectedBranchId && String(b.id) === String(selectedBranchId)) {
                opt.selected = true;
            }
            targetSelect.append(opt);
        });
        targetSelect.disabled = false;
    } catch (err) {
        targetSelect.replaceChildren(new Option('Could not load branches', ''));
    }
}

async function handleFilterOrgChange() {
    const orgId = filterOrgSelect.value;
    if (!orgId) {
        filterBranchSelect.replaceChildren(new Option('Select organization first', ''));
        filterBranchSelect.disabled = true;
    } else {
        await loadBranchesForOrg(orgId, filterBranchSelect, 'All branches in organization');
    }
    await loadList(1);
}

function renderRow(employee) {
    const row = table.tBodies[0].insertRow();
    const contact = [employee.mobile_number, employee.email].filter(Boolean).join(' • ') || '—';
    const orgDisplay = employee.organization ? `${employee.organization.organization_name} (${employee.organization.organization_code})` : '—';
    const branchDisplay = employee.branch ? `${employee.branch.branch_name} (${employee.branch.branch_code})` : '—';

    const cells = [
        employee.employee_id,
        employee.name,
        employee.designation,
        orgDisplay,
        branchDisplay,
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
    badge.className = `badge ${employee.is_active ? 'badge-active' : 'badge-inactive'}`;
    badge.textContent = employee.is_active ? 'Active' : 'Inactive';
    statusCell.append(badge);

    // Actions
    const actionCell = row.insertCell();
    actionCell.dataset.label = 'Actions';
    const buttons = document.createElement('div');
    buttons.className = 'actions';

    const editBtn = actionBtn('Edit');
    editBtn.addEventListener('click', () => openEdit(employee));

    const delBtn = actionBtn('Delete', 'danger');
    delBtn.addEventListener('click', () => openDelete(employee));

    buttons.append(editBtn, delBtn);
    actionCell.append(buttons);
}

async function loadList(targetPage = 1) {
    if (loading) return;
    loading = true;
    page = targetPage;

    status.hidden = false;
    status.textContent = 'Loading employees…';
    error.hidden = true;
    table.hidden = true;
    pagination.hidden = true;
    table.tBodies[0].replaceChildren();

    const params = new URLSearchParams({ page: String(page) });

    if (filterOrgSelect.value) params.set('organization_id', filterOrgSelect.value);
    if (filterBranchSelect.value) params.set('branch_id', filterBranchSelect.value);
    if (statusFilter.value !== '') params.set('is_active', statusFilter.value);
    if (searchFilter.value.trim() !== '') params.set('search', searchFilter.value.trim());

    try {
        const res = await api(`${apiUrl}?${params.toString()}`);
        const items = res.data ?? [];
        const meta = res.meta ?? {};

        if (items.length === 0) {
            status.textContent = 'No employees found matching the filter criteria.';
            if (listCount) listCount.textContent = '0 employees';
            loading = false;
            return;
        }

        status.hidden = true;
        table.hidden = false;
        items.forEach(renderRow);

        if (listCount) {
            listCount.textContent = `${meta.total ?? items.length} employee${(meta.total ?? items.length) === 1 ? '' : 's'}`;
        }

        if (meta.last_page > 1) {
            pagination.hidden = false;
            document.getElementById('currentPage').textContent = `Page ${meta.current_page} of ${meta.last_page}`;
            previous.disabled = meta.current_page <= 1;
            next.disabled = meta.current_page >= meta.last_page;
        }
    } catch (err) {
        status.hidden = true;
        error.hidden = false;
        document.getElementById('listErrorText').textContent = err.message || 'Failed to load employees.';
    } finally {
        loading = false;
    }
}

function openCreate() {
    editingId = null;
    clearValidationErrors();
    employeeForm.reset();
    employeeDialogTitle.textContent = 'New employee';
    saveEmployeeBtn.textContent = 'Save employee';
    populateFormOrgDropdown();
    employeeBranchSelect.replaceChildren(new Option('Select organization first', ''));
    employeeBranchSelect.disabled = true;
    employeeDialog.showModal();
}

async function openEdit(employee) {
    editingId = employee.id;
    clearValidationErrors();
    employeeDialogTitle.textContent = `Edit employee: ${employee.name}`;
    saveEmployeeBtn.textContent = 'Update employee';

    populateFormOrgDropdown(employee.organization);
    employeeOrgSelect.value = employee.organization_id;

    await loadBranchesForOrg(employee.organization_id, employeeBranchSelect, 'Select branch', employee.branch_id);

    employeeForm.elements['employee_id'].value = employee.employee_id;
    employeeForm.elements['name'].value = employee.name;
    employeeForm.elements['designation'].value = employee.designation;
    employeeForm.elements['mobile_number'].value = employee.mobile_number;
    employeeForm.elements['email'].value = employee.email;
    employeeForm.elements['is_active'].checked = Boolean(employee.is_active);

    employeeDialog.showModal();
}

async function handleFormSubmit(e) {
    e.preventDefault();
    clearValidationErrors();
    saveEmployeeBtn.disabled = true;
    saveEmployeeBtn.textContent = 'Saving…';

    const orgVal = employeeForm.elements['organization_id'].value;
    const branchVal = employeeForm.elements['branch_id'].value;

    const payload = {
        organization_id: orgVal ? parseInt(orgVal, 10) : null,
        branch_id: branchVal ? parseInt(branchVal, 10) : null,
        employee_id: employeeForm.elements['employee_id'].value.trim(),
        name: employeeForm.elements['name'].value.trim(),
        designation: employeeForm.elements['designation'].value.trim(),
        mobile_number: employeeForm.elements['mobile_number'].value.trim(),
        email: employeeForm.elements['email'].value.trim(),
        is_active: Boolean(employeeForm.elements['is_active'].checked),
    };

    try {
        if (editingId) {
            await api(`${apiUrl}/${editingId}`, {
                method: 'PUT',
                data: payload,
            });
            showSuccess(`Employee "${payload.name}" updated successfully.`);
        } else {
            await api(apiUrl, {
                method: 'POST',
                data: payload,
            });
            showSuccess(`Employee "${payload.name}" added successfully.`);
        }

        employeeDialog.close();
        await loadList(editingId ? page : 1);
    } catch (err) {
        const validationErrors = err.fields || err.errors;
        if (validationErrors) {
            displayValidationErrors(validationErrors);
        } else {
            employeeFormError.textContent = err.message || 'An error occurred while saving.';
            employeeFormError.hidden = false;
        }
    } finally {
        saveEmployeeBtn.disabled = false;
        saveEmployeeBtn.textContent = editingId ? 'Update employee' : 'Save employee';
    }
}

function openDelete(employee) {
    deleteTarget = employee;
    deleteError.hidden = true;
    deleteDescription.textContent = `Are you sure you want to delete employee "${employee.name}" (${employee.employee_id})? This action cannot be undone.`;
    deleteDialog.showModal();
}

async function handleConfirmDelete() {
    if (!deleteTarget) return;
    confirmDelete.disabled = true;
    confirmDelete.textContent = 'Deleting…';

    try {
        await api(`${apiUrl}/${deleteTarget.id}`, { method: 'DELETE' });
        deleteDialog.close();
        showSuccess(`Employee "${deleteTarget.name}" deleted successfully.`);
        deleteTarget = null;
        await loadList(page);
    } catch (err) {
        deleteError.textContent = err.message || 'Could not delete employee.';
        deleteError.hidden = false;
    } finally {
        confirmDelete.disabled = false;
        confirmDelete.textContent = 'Delete employee';
    }
}

// Event Listeners
openCreateBtn?.addEventListener('click', openCreate);
closeEmployeeDialog?.addEventListener('click', () => employeeDialog.close());
cancelEmployeeForm?.addEventListener('click', () => employeeDialog.close());
employeeForm?.addEventListener('submit', handleFormSubmit);

employeeOrgSelect?.addEventListener('change', () => {
    const orgId = employeeOrgSelect.value;
    loadBranchesForOrg(orgId, employeeBranchSelect, 'Select branch');
});

filterOrgSelect?.addEventListener('change', handleFilterOrgChange);
filterBranchSelect?.addEventListener('change', () => loadList(1));
statusFilter?.addEventListener('change', () => loadList(1));

searchFilter?.addEventListener('input', () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => loadList(1), 300);
});

resetFilterBtn?.addEventListener('click', async () => {
    filterOrgSelect.value = '';
    filterBranchSelect.replaceChildren(new Option('Select organization first', ''));
    filterBranchSelect.disabled = true;
    statusFilter.value = '';
    searchFilter.value = '';
    await loadList(1);
});

cancelDelete?.addEventListener('click', () => deleteDialog.close());
confirmDelete?.addEventListener('click', handleConfirmDelete);

previous?.addEventListener('click', () => { if (page > 1) loadList(page - 1); });
next?.addEventListener('click', () => { loadList(page + 1); });
document.getElementById('retryList')?.addEventListener('click', () => loadList(page));

// Initial bootstrap
(async function init() {
    await loadActiveOrganizations();
    await loadList(1);
})();
