import { api, formatDate, leadLabels } from './workspace.js';

const root = document.getElementById('leadList');
const listUrl = root.dataset.listUrl;
const orgsUrl = root.dataset.orgsUrl;
const branchesUrl = root.dataset.branchesUrl;
const employeesUrl = root.dataset.employeesUrl;
const table = document.getElementById('leadTable');
const status = document.getElementById('listStatus');
const error = document.getElementById('listError');
const success = document.getElementById('listSuccess');
const pagination = document.getElementById('listPagination');
const previous = document.getElementById('previousPage');
const next = document.getElementById('nextPage');
const filterOrgSelect = document.getElementById('filterOrgSelect');
const filterBranchSelect = document.getElementById('filterBranchSelect');
const filterEmployeeSelect = document.getElementById('filterEmployeeSelect');
const resetFiltersBtn = document.getElementById('resetFiltersBtn');

// Detail Dialog
const detailDialog = document.getElementById('detailDialog');
const detailStatus = document.getElementById('detailStatus');
const detailError = document.getElementById('detailError');
const details = document.getElementById('leadDetails');

// Delete Dialog
const deleteDialog = document.getElementById('deleteDialog');
const deleteError = document.getElementById('deleteError');
const cancelDelete = document.getElementById('cancelDelete');
const confirmDelete = document.getElementById('confirmDelete');

// Assign Dialog
const assignDialog = document.getElementById('assignDialog');
const assignTitle = document.getElementById('assignTitle');
const closeAssignDialog = document.getElementById('closeAssignDialog');
const cancelAssignBtn = document.getElementById('cancelAssignBtn');
const confirmAssignBtn = document.getElementById('confirmAssignBtn');
const assignForm = document.getElementById('assignForm');
const assignError = document.getElementById('assignError');
const assignSummary = document.getElementById('assignSummary');
const assignEmployeeSelect = document.getElementById('assignEmployeeSelect');
const assignRemarks = document.getElementById('assignRemarks');

let page = 1;
let requestedPage = 1;
let loading = false;
let detailRequest;
let deleteTarget;
let assignTarget;

function leadUrl(id) {
    return `${listUrl}/${encodeURIComponent(id)}`;
}

function pageUrl(number) {
    const url = new URL(listUrl);
    url.searchParams.set('page', number);
    if (filterOrgSelect?.value) {
        url.searchParams.set('organization_id', filterOrgSelect.value);
    }
    if (filterBranchSelect?.value) {
        url.searchParams.set('branch_id', filterBranchSelect.value);
    }
    if (filterEmployeeSelect?.value) {
        url.searchParams.set('assigned_employee_id', filterEmployeeSelect.value);
    }
    return url;
}

function action(tag, label, lead, style = 'secondary') {
    const element = document.createElement(tag);
    element.className = `btn btn-${style}`;
    element.textContent = label;
    element.setAttribute('aria-label', `${label} lead for ${lead.customer_name}`);
    if (tag === 'button') element.type = 'button';
    return element;
}

function renderRow(lead) {
    const row = table.tBodies[0].insertRow();
    let branchDealer = '—';
    if (lead.branch && lead.organization) {
        branchDealer = `${lead.branch.branch_name} (${lead.organization.organization_code})`;
    } else if (lead.branch) {
        branchDealer = lead.branch.branch_name;
    } else if (lead.organization) {
        branchDealer = lead.organization.organization_name;
    }

    let assignedDisplay = 'Unassigned';
    if (lead.assigned_employee) {
        assignedDisplay = `${lead.assigned_employee.name} (${lead.assigned_employee.designation})`;
    }

    const columns = [
        lead.customer_name,
        lead.mobile,
        branchDealer,
        assignedDisplay,
        lead.recommended_model,
        lead.priority,
        formatDate(lead.follow_up_date),
    ];
    const headers = table.tHead.rows[0].cells;
    columns.forEach((value, index) => {
        const cell = row.insertCell();
        cell.dataset.label = headers[index].textContent;
        if (index === 3 && !lead.assigned_employee) {
            const badge = document.createElement('span');
            badge.className = 'badge badge-inactive';
            badge.textContent = 'Unassigned';
            cell.append(badge);
        } else {
            cell.textContent = value;
        }
    });

    const actions = row.insertCell();
    actions.dataset.label = 'Actions';
    const buttons = document.createElement('div');
    buttons.className = 'actions';

    const view = action('button', 'View', lead);
    view.addEventListener('click', () => viewLead(lead.id));

    const assignBtn = action('button', lead.assigned_employee ? 'Reassign' : 'Assign', lead);
    assignBtn.addEventListener('click', () => openAssign(lead));

    const edit = action('a', 'Edit', lead);
    edit.href = `${root.dataset.editBase}/${encodeURIComponent(lead.id)}/edit`;

    const remove = action('button', 'Delete', lead, 'danger');
    remove.addEventListener('click', () => openDelete(lead));

    buttons.append(view, assignBtn, edit, remove);
    actions.append(buttons);
}

async function loadList(number = page) {
    if (loading) return;
    loading = true;
    requestedPage = number;
    status.textContent = 'Loading registered leads…';
    status.hidden = false;
    error.hidden = true;
    table.hidden = true;
    pagination.hidden = true;

    try {
        let result = await api(pageUrl(number));
        if (number > result.meta.last_page) {
            result = await api(pageUrl(result.meta.last_page));
        }
        const meta = result.meta;
        page = meta.current_page;
        table.tBodies[0].replaceChildren();
        result.data.forEach(renderRow);

        document.getElementById('listCount').textContent = `${meta.total} ${meta.total === 1 ? 'lead' : 'leads'}`;
        table.hidden = result.data.length === 0;
        status.textContent = 'No customer leads match the selected filter criteria.';
        status.hidden = result.data.length > 0;
        pagination.hidden = meta.last_page <= 1;
        document.getElementById('currentPage').textContent = `Page ${page} of ${meta.last_page}`;
        previous.disabled = page <= 1;
        next.disabled = page >= meta.last_page;
    } catch (failure) {
        status.hidden = true;
        document.getElementById('listCount').textContent = '';
        document.getElementById('listErrorText').textContent = failure.message;
        error.hidden = false;
    } finally {
        loading = false;
    }
}

async function viewLead(id) {
    const request = new AbortController();
    detailRequest = request;
    details.replaceChildren();
    detailError.hidden = true;
    detailStatus.textContent = 'Loading lead details…';
    detailStatus.hidden = false;
    document.getElementById('detailTitle').textContent = `Lead #${id}`;
    detailDialog.showModal();

    try {
        const { data: lead } = await api(leadUrl(id), { signal: request.signal });
        if (request.signal.aborted) return;
        for (const [name, label] of Object.entries(leadLabels)) {
            const field = document.createElement('div');
            if (name === 'remarks') field.className = 'field full';
            const term = document.createElement('dt');
            term.textContent = label;
            const value = document.createElement('dd');
            let displayVal = lead[name] ?? '—';
            if (name === 'organization') {
                displayVal = lead.organization ? `${lead.organization.organization_name} (${lead.organization.organization_code})` : '—';
            } else if (name === 'branch') {
                displayVal = lead.branch ? `${lead.branch.branch_name} (${lead.branch.branch_code})` : '—';
            } else if (name === 'follow_up_date') {
                displayVal = formatDate(lead.follow_up_date);
            }
            value.textContent = displayVal;
            field.append(term, value);
            details.append(field);
        }

        // Assigned Employee Card
        const assignedField = document.createElement('div');
        assignedField.className = 'field full';
        const assignedTerm = document.createElement('dt');
        assignedTerm.textContent = 'Assigned Employee';
        const assignedVal = document.createElement('dd');
        if (lead.assigned_employee) {
            assignedVal.innerHTML = `<strong>${lead.assigned_employee.name}</strong> (${lead.assigned_employee.designation})<br><small>Mobile: ${lead.assigned_employee.mobile_number} • Email: ${lead.assigned_employee.email}</small>`;
        } else {
            assignedVal.innerHTML = '<span class="badge badge-inactive">Unassigned</span>';
        }
        assignedField.append(assignedTerm, assignedVal);
        details.append(assignedField);

        // Assignment History Section
        if (lead.assignment_history && lead.assignment_history.length > 0) {
            const historyField = document.createElement('div');
            historyField.className = 'field full';
            const historyTerm = document.createElement('dt');
            historyTerm.textContent = 'Assignment History';
            const historyVal = document.createElement('dd');

            const list = document.createElement('ul');
            list.style.cssText = 'padding-left: 20px; margin: 5px 0;';
            lead.assignment_history.forEach(item => {
                const li = document.createElement('li');
                const prevName = item.previous_employee ? item.previous_employee.name : 'Unassigned';
                const newName = item.new_employee ? item.new_employee.name : 'Unassigned';
                const remarkText = item.remarks ? ` — <em>"${item.remarks}"</em>` : '';
                li.innerHTML = `<strong>${prevName} → ${newName}</strong> on ${item.assigned_at_formatted ?? item.assigned_at} by ${item.assigned_by}${remarkText}`;
                list.append(li);
            });
            historyVal.append(list);
            historyField.append(historyTerm, historyVal);
            details.append(historyField);
        }
    } catch (failure) {
        if (request.signal.aborted) return;
        detailError.textContent = failure.message;
        detailError.hidden = false;
    } finally {
        if (!request.signal.aborted) detailStatus.hidden = true;
    }
}

async function openAssign(lead) {
    assignTarget = lead;
    assignError.hidden = true;
    assignError.textContent = '';
    assignTitle.textContent = lead.assigned_employee ? `Reassign Lead #${lead.id}` : `Assign Lead #${lead.id}`;
    confirmAssignBtn.textContent = lead.assigned_employee ? 'Reassign lead' : 'Assign lead';

    let orgName = lead.organization ? lead.organization.organization_name : 'No organization';
    let branchName = lead.branch ? lead.branch.branch_name : 'No branch';
    let currentAssigned = lead.assigned_employee
        ? `Currently assigned to: <strong>${lead.assigned_employee.name}</strong> (${lead.assigned_employee.designation})`
        : 'Currently: <strong>Unassigned</strong>';

    assignSummary.innerHTML = `<strong>Customer:</strong> ${lead.customer_name} (${lead.mobile})<br><strong>Location:</strong> ${orgName} • ${branchName}<br>${currentAssigned}`;

    assignEmployeeSelect.replaceChildren(new Option('Loading employees…', ''));
    assignEmployeeSelect.disabled = true;
    assignRemarks.value = '';

    assignDialog.showModal();

    try {
        let fetchUrl = `${employeesUrl}?active=1&all=1`;
        if (lead.branch_id) {
            fetchUrl += `&branch_id=${encodeURIComponent(lead.branch_id)}`;
        } else if (lead.organization_id) {
            fetchUrl += `&organization_id=${encodeURIComponent(lead.organization_id)}`;
        }

        const res = await api(fetchUrl);
        const employees = res.data ?? [];

        assignEmployeeSelect.replaceChildren(new Option('Select sales employee…', ''));
        if (employees.length === 0) {
            assignEmployeeSelect.append(new Option('No active employees found for this branch', ''));
        } else {
            employees.forEach(emp => {
                const opt = new Option(`${emp.name} (${emp.designation}) - ${emp.employee_id}`, emp.id);
                if (lead.assigned_employee_id && String(emp.id) === String(lead.assigned_employee_id)) {
                    opt.selected = true;
                }
                assignEmployeeSelect.append(opt);
            });
            assignEmployeeSelect.disabled = false;
        }
    } catch (err) {
        assignEmployeeSelect.replaceChildren(new Option('Error loading employees', ''));
    }
}

async function handleAssignSubmit(e) {
    e.preventDefault();
    if (!assignTarget) return;

    confirmAssignBtn.disabled = true;
    confirmAssignBtn.textContent = 'Saving…';
    assignError.hidden = true;

    const payload = {
        employee_id: assignEmployeeSelect.value,
        remarks: assignRemarks.value.trim() || null,
    };

    try {
        await api(`/api/leads/${assignTarget.id}/assign`, {
            method: 'POST',
            data: payload,
        });
        assignDialog.close();
        success.textContent = `Lead #${assignTarget.id} successfully assigned.`;
        success.hidden = false;
        await loadList(page);
    } catch (err) {
        assignError.textContent = err.message || 'Failed to assign lead.';
        assignError.hidden = false;
    } finally {
        confirmAssignBtn.disabled = false;
        confirmAssignBtn.textContent = assignTarget.assigned_employee ? 'Reassign lead' : 'Assign lead';
    }
}

function openDelete(lead) {
    deleteTarget = lead;
    deleteError.hidden = true;
    document.getElementById('deleteDescription').textContent = `This will permanently delete the registered lead for ${lead.customer_name}.`;
    deleteDialog.showModal();
}

async function deleteLead() {
    if (!deleteTarget || confirmDelete.disabled) return;
    confirmDelete.disabled = true;
    cancelDelete.disabled = true;
    confirmDelete.textContent = 'Deleting…';
    deleteError.hidden = true;

    try {
        await api(leadUrl(deleteTarget.id), { method: 'DELETE' });
        deleteDialog.close();
        success.textContent = 'Customer lead deleted successfully.';
        success.hidden = false;
        await loadList();
    } catch (failure) {
        deleteError.textContent = failure.message;
        deleteError.hidden = false;
    } finally {
        confirmDelete.disabled = false;
        cancelDelete.disabled = false;
        confirmDelete.textContent = 'Delete lead';
    }
}

async function initFilters() {
    if (!filterOrgSelect || !orgsUrl) return;
    try {
        const res = await api(`${orgsUrl}?active=1&all=1`);
        const orgs = res.data ?? [];
        filterOrgSelect.replaceChildren(new Option('All organizations', ''));
        orgs.forEach(org => {
            filterOrgSelect.append(new Option(`${org.organization_name} (${org.organization_code})`, org.id));
        });
    } catch (err) {
        // Fallback to default
    }
}

async function loadFilterEmployees(orgId = null, branchId = null) {
    if (!filterEmployeeSelect || !employeesUrl) return;
    try {
        let url = `${employeesUrl}?active=1&all=1`;
        if (branchId) {
            url += `&branch_id=${encodeURIComponent(branchId)}`;
        } else if (orgId) {
            url += `&organization_id=${encodeURIComponent(orgId)}`;
        }

        const res = await api(url);
        const employees = res.data ?? [];
        filterEmployeeSelect.replaceChildren(
            new Option('All employees', ''),
            new Option('Unassigned only', 'unassigned')
        );
        employees.forEach(emp => {
            filterEmployeeSelect.append(new Option(`${emp.name} (${emp.designation})`, emp.id));
        });
    } catch (err) {
        // Fallback
    }
}

async function onFilterOrgChange() {
    const orgId = filterOrgSelect.value;
    filterBranchSelect.replaceChildren(new Option('All branches', ''));
    if (!orgId) {
        filterBranchSelect.disabled = true;
        await loadFilterEmployees();
        await loadList(1);
        return;
    }

    filterBranchSelect.disabled = true;
    try {
        const res = await api(`${branchesUrl}?organization_id=${encodeURIComponent(orgId)}&active=1&all=1`);
        const branches = res.data ?? [];
        filterBranchSelect.replaceChildren(new Option('All branches', ''));
        branches.forEach(branch => {
            filterBranchSelect.append(new Option(`${branch.branch_name} (${branch.branch_code})`, branch.id));
        });
        filterBranchSelect.disabled = false;
        await loadFilterEmployees(orgId);
    } catch (err) {
        filterBranchSelect.replaceChildren(new Option('All branches', ''));
        filterBranchSelect.disabled = true;
    }
    await loadList(1);
}

async function onFilterBranchChange() {
    const branchId = filterBranchSelect.value;
    const orgId = filterOrgSelect.value;
    await loadFilterEmployees(orgId, branchId);
    await loadList(1);
}

async function resetFilters() {
    if (filterOrgSelect) filterOrgSelect.value = '';
    if (filterBranchSelect) {
        filterBranchSelect.replaceChildren(new Option('All branches', ''));
        filterBranchSelect.disabled = true;
    }
    if (filterEmployeeSelect) {
        filterEmployeeSelect.replaceChildren(
            new Option('All employees', ''),
            new Option('Unassigned only', 'unassigned')
        );
        filterEmployeeSelect.value = '';
    }
    await loadList(1);
}

detailDialog.addEventListener('close', () => detailRequest?.abort());
deleteDialog.addEventListener('cancel', event => {
    if (confirmDelete.disabled) event.preventDefault();
});
cancelDelete.addEventListener('click', () => deleteDialog.close());
confirmDelete.addEventListener('click', deleteLead);
document.getElementById('retryList').addEventListener('click', () => loadList(requestedPage));
previous.addEventListener('click', () => loadList(page - 1));
next.addEventListener('click', () => loadList(page + 1));
filterOrgSelect?.addEventListener('change', onFilterOrgChange);
filterBranchSelect?.addEventListener('change', onFilterBranchChange);
filterEmployeeSelect?.addEventListener('change', () => loadList(1));
resetFiltersBtn?.addEventListener('click', resetFilters);

closeAssignDialog?.addEventListener('click', () => assignDialog.close());
cancelAssignBtn?.addEventListener('click', () => assignDialog.close());
assignForm?.addEventListener('submit', handleAssignSubmit);

initFilters();
loadFilterEmployees();
loadList();
