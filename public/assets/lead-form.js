import { api, formatDate, leadLabels, workspaceToday } from './workspace.js';

const form = document.getElementById('leadForm');
const fields = document.getElementById('formFields');
const status = document.getElementById('formStatus');
const errorBox = document.getElementById('formError');
const errorText = document.getElementById('formErrorText');
const retry = document.getElementById('retryForm');
const success = document.getElementById('success');
const submit = form.querySelector('[type="submit"]');
const reset = form.querySelector('[type="reset"]');

// Form elements
const company = form.elements.company_name;
const hotspot = form.elements.hotspot;
const model = form.elements.recommended_model;
const followUp = form.elements.follow_up_date;
const choiceGroups = form.querySelectorAll('[data-choices]');
const editing = form.dataset.editing === 'true';
const submitLabel = submit.textContent;

// CRM elements
const crmCompany = form.elements.company_id;
const crmBranch = form.elements.branch_id;
const branchName = form.elements.branch_name;
const assignedEmployee = form.elements.assigned_employee_id;
const enquiryType = form.elements.enquiry_type_id;
const enquiryTypeName = form.elements.enquiry_type;
const crmLeadSource = form.elements.lead_source_id;
const vehicleModelId = form.elements.vehicle_model_id;
const crmVariant = form.elements.variant_id;
const variantName = form.elements.variant_name;
const crmLeadStatus = form.elements.lead_status_id;
const leadStatusName = form.elements.lead_status;
const crmStatusResponse = form.elements.status_response_id;
const statusResponseName = form.elements.status_response;

let catalog;
let crmModelsList = [];
let savedLead = null;
let saving = false;

function options(field, scope = '') {
    return (catalog[field] ?? []).filter(option => option.scope === scope);
}

async function loadEmployees(endpoint) {
    const url = new URL(endpoint, window.location.href);
    url.searchParams.set('per_page', '100');
    const employees = [];
    let page = 1;
    let lastPage;
    do {
        url.searchParams.set('page', String(page));
        const result = await api(url);
        employees.push(...result.data);
        lastPage = result.pagination?.last_page ?? 1;
        page++;
    } while (page <= lastPage);
    return employees;
}

function fillSelect(select, choices, placeholder) {
    if (!select) return;
    const prompt = placeholder ?? select.options[0]?.text ?? 'Select';
    select.replaceChildren(new Option(prompt, ''), ...choices.map(option => new Option(option.value, option.value)));
}

function renderChoices(group) {
    const name = group.dataset.choices;
    group.replaceChildren(...options(name).map(option => {
        const label = document.createElement('label');
        label.className = 'choice';
        const radio = document.createElement('input');
        radio.type = 'radio';
        radio.name = group.dataset.input || name;
        radio.value = option.value;
        radio.required = group.hasAttribute('data-required');
        const text = document.createElement('span');
        if (name === 'priority') {
            const title = document.createElement('b');
            const description = document.createElement('small');
            title.textContent = option.value;
            description.textContent = option.description;
            text.append(title, description);
        } else {
            text.textContent = option.value;
        }
        label.append(radio, text);
        return label;
    }));
}

function syncCompany() {
    const type = options('business_type').find(option => option.value === form.elements.business_type.value);
    const required = type?.requires_company === true;
    company.closest('.field').classList.toggle('hidden', !required);
    company.required = required;
    company.disabled = !required;
    if (!required) company.value = '';
}

function syncModels(application) {
    const hasApplication = Boolean(application);
    model.disabled = !hasApplication;
    if (!hasApplication) {
        model.replaceChildren(new Option('Select application first', ''));
        return;
    }

    const localModels = options('recommended_model', application).map(o => ({
        id: '',
        name: o.value,
        segment: application,
    }));

    // Combine CRM models and local models without duplicate names
    const seen = new Set();
    const combined = [];
    for (const m of crmModelsList) {
        seen.add(m.name.toLowerCase());
        combined.push({ id: m.id, name: m.name, segment: m.segment?.name ?? '' });
    }
    for (const m of localModels) {
        if (!seen.has(m.name.toLowerCase())) {
            combined.push(m);
        }
    }

    model.replaceChildren(
        new Option('Select recommended model', ''),
        ...combined.map(m => {
            const label = m.segment ? `${m.name} (${m.segment})` : m.name;
            const opt = new Option(label, m.name);
            opt.dataset.modelId = m.id || '';
            return opt;
        })
    );
}

function syncApplication(application) {
    const hasApplication = Boolean(application);
    const name = application ? application.toLowerCase() : '';
    document.getElementById('hotspotLabel').textContent = `${application} hotspots`;
    hotspot.closest('.field').classList.toggle('hidden', !hasApplication);
    hotspot.required = hasApplication;
    hotspot.disabled = !hasApplication;
    fillSelect(hotspot, hasApplication ? options('hotspot', application) : [],
        hasApplication ? `Select ${name} hotspot` : 'Select hotspot');
    syncModels(application);
}

function syncDate() {
    const today = workspaceToday();
    const originalDate = editing ? savedLead?.follow_up_date : null;
    followUp.min = originalDate && originalDate < today && followUp.value === originalDate ? originalDate : today;
    document.getElementById('todayLabel').textContent = formatDate(today);
}

async function loadBranches(companyId, selectedBranchId = null) {
    if (!crmBranch) return;
    if (!companyId) {
        crmBranch.replaceChildren(new Option('Select company first', ''));
        crmBranch.disabled = true;
        branchName.value = '';
        return;
    }
    crmBranch.replaceChildren(new Option('Loading branches…', ''));
    crmBranch.disabled = true;
    try {
        const res = await api(`/api/mahindra/companies/${companyId}/branches`);
        const branches = res.data ?? [];
        crmBranch.replaceChildren(
            new Option(branches.length ? 'Select dealer branch' : 'No branches found', ''),
            ...branches.map(b => new Option(b.city ? `${b.name} (${b.city})` : b.name, b.id))
        );
        crmBranch.disabled = false;
        if (selectedBranchId) {
            crmBranch.value = String(selectedBranchId);
            const opt = crmBranch.selectedOptions[0];
            branchName.value = opt ? opt.text : '';
        }
    } catch {
        crmBranch.replaceChildren(new Option('Branches unavailable', ''));
    }
}

async function loadSources(typeId, selectedSourceId = null) {
    if (!crmLeadSource) return;
    const enquiryOpt = enquiryType.selectedOptions[0];
    enquiryTypeName.value = enquiryOpt && enquiryOpt.value ? enquiryOpt.text : '';
    if (!typeId) {
        crmLeadSource.replaceChildren(new Option('Select enquiry type first', ''));
        crmLeadSource.disabled = true;
        return;
    }
    crmLeadSource.replaceChildren(new Option('Loading sources…', ''));
    crmLeadSource.disabled = true;
    try {
        const res = await api(`/api/mahindra/enquiry-types/${typeId}/sources`);
        if (String(enquiryType.value) !== String(typeId)) return;
        const sources = res.data ?? [];
        crmLeadSource.replaceChildren(
            new Option(sources.length ? 'Select enquiry source' : 'No sources found', ''),
            ...sources.map(s => new Option(s.campaign, s.id))
        );
        crmLeadSource.disabled = false;
        if (selectedSourceId) {
            crmLeadSource.value = String(selectedSourceId);
        }
    } catch {
        if (String(enquiryType.value) === String(typeId)) {
            crmLeadSource.replaceChildren(new Option('Sources unavailable', ''));
        }
    }
}

async function loadVariants(mId, selectedVariantId = null) {
    if (!crmVariant) return;
    if (!mId) {
        crmVariant.replaceChildren(new Option('Select model first', ''));
        crmVariant.disabled = true;
        variantName.value = '';
        return;
    }
    crmVariant.replaceChildren(new Option('Loading variants…', ''));
    crmVariant.disabled = true;
    try {
        const res = await api(`/api/mahindra/vehicle-models/${mId}/variants`);
        const variants = res.data ?? [];
        crmVariant.replaceChildren(
            new Option(variants.length ? 'Select variant' : 'No variants listed', ''),
            ...variants.map(v => new Option(v.name, v.id))
        );
        crmVariant.disabled = !variants.length;
        if (selectedVariantId) {
            crmVariant.value = String(selectedVariantId);
            const opt = crmVariant.selectedOptions[0];
            variantName.value = opt ? opt.text : '';
        }
    } catch {
        crmVariant.replaceChildren(new Option('Variants unavailable', ''));
    }
}

async function loadResponses(statusId, selectedResponseId = null) {
    if (!crmStatusResponse) return;
    const statusOpt = crmLeadStatus.selectedOptions[0];
    leadStatusName.value = statusOpt && statusOpt.value ? statusOpt.text : '';
    if (!statusId) {
        crmStatusResponse.replaceChildren(new Option('Select status first', ''));
        crmStatusResponse.disabled = true;
        statusResponseName.value = '';
        return;
    }
    crmStatusResponse.replaceChildren(new Option('Loading responses…', ''));
    crmStatusResponse.disabled = true;
    try {
        const res = await api(`/api/mahindra/lead-statuses/${statusId}/responses`);
        const responses = res.data ?? [];
        crmStatusResponse.replaceChildren(
            new Option(responses.length ? 'Select status response' : 'No responses found', ''),
            ...responses.map(r => new Option(r.response, r.id))
        );
        crmStatusResponse.disabled = !responses.length;
        if (selectedResponseId) {
            crmStatusResponse.value = String(selectedResponseId);
            const opt = crmStatusResponse.selectedOptions[0];
            statusResponseName.value = opt ? opt.text : '';
        }
    } catch {
        crmStatusResponse.replaceChildren(new Option('Responses unavailable', ''));
    }
}

function populate(lead = {}) {
    syncApplication(lead.application ?? '');
    for (const control of fields.elements) {
        if (!control.name) continue;
        const value = control.name === 'lead_source_channel'
            ? (lead.lead_source_channel ?? lead.lead_source ?? '')
            : lead[control.name] ?? '';
        if (control.type === 'radio') {
            control.checked = control.value === value;
        } else if (control.type !== 'file') {
            control.value = value;
        }
    }

    // Populate model and match vehicle_model_id
    if (lead.recommended_model) {
        model.value = lead.recommended_model;
        const opt = model.selectedOptions[0];
        if (opt?.dataset.modelId) {
            vehicleModelId.value = opt.dataset.modelId;
        }
    }
    if (lead.vehicle_model_id) {
        vehicleModelId.value = lead.vehicle_model_id;
        loadVariants(lead.vehicle_model_id, lead.variant_id);
    }

    // Cascading CRM dropdowns
    if (lead.company_id) {
        crmCompany.value = String(lead.company_id);
        loadBranches(lead.company_id, lead.branch_id);
    }
    if (lead.enquiry_type_id) {
        enquiryType.value = String(lead.enquiry_type_id);
        loadSources(lead.enquiry_type_id, lead.lead_source_id);
    }
    if (lead.lead_status_id) {
        crmLeadStatus.value = String(lead.lead_status_id);
        loadResponses(lead.lead_status_id, lead.status_response_id);
    }
    if (lead.assigned_employee_id) {
        const id = String(lead.assigned_employee_id);
        if (!Array.from(assignedEmployee.options).some(option => option.value === id)) {
            const option = new Option(lead.assigned_employee_name || 'Consultant #' + id, id);
            option.dataset.name = lead.assigned_employee_name || '';
            assignedEmployee.add(option);
        }
        assignedEmployee.value = id;
    }

    syncCompany();
    syncDate();
}

function clearErrors() {
    errorBox.hidden = true;
    for (const message of form.querySelectorAll('.field-error')) message.remove();
    for (const control of form.querySelectorAll('[aria-invalid="true"]')) {
        control.removeAttribute('aria-invalid');
        if (control.dataset.originalDescription) {
            control.setAttribute('aria-describedby', control.dataset.originalDescription);
        } else {
            control.removeAttribute('aria-describedby');
        }
    }
}

function showError(error, allowRetry = false) {
    errorText.textContent = error.message;
    retry.hidden = !allowRetry;
    errorBox.hidden = false;
    let firstControl;
    for (const [name, messages] of Object.entries(error.fields ?? {})) {
        const controls = form.querySelectorAll(`[name="${CSS.escape(name)}"]`);
        if (!controls.length) continue;
        const message = document.createElement('p');
        message.className = 'field-error';
        message.id = `error-${name}`;
        message.textContent = messages[0];
        controls[0].closest('.field').append(message);
        for (const control of controls) {
            control.dataset.originalDescription = control.getAttribute('aria-describedby') ?? '';
            control.setAttribute('aria-invalid', 'true');
            control.setAttribute('aria-describedby', `${control.dataset.originalDescription} ${message.id}`.trim());
        }
        firstControl ??= controls[0];
    }
    (firstControl ?? errorBox).focus();
}

function showSuccess(lead) {
    const headline = document.createElement('strong');
    headline.textContent = editing ? 'Lead details updated successfully.' : 'Lead details saved successfully.';
    const details = document.createElement('div');
    details.className = 'saved-details';
    details.textContent = Object.entries(leadLabels)
        .filter(([name]) => lead[name] !== null && lead[name] !== undefined && lead[name] !== '')
        .map(([name, label]) => `${label}: ${lead[name]}`).join('\n');
    const copy = document.createElement('button');
    copy.type = 'button';
    copy.className = 'btn btn-secondary';
    copy.textContent = 'Copy lead details';
    copy.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(details.textContent);
            copy.textContent = 'Copied';
        } catch {
            copy.textContent = 'Copy unavailable';
        }
    });
    success.replaceChildren(headline, details, copy);
    success.hidden = false;
    success.focus({ preventScroll: true });
    success.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

async function loadForm() {
    clearErrors();
    status.textContent = 'Loading lead form & Mahindra CRM master data…';
    status.hidden = false;
    form.setAttribute('aria-busy', 'true');
    try {
        const [choices, lead, crmCompanies, crmEmployees, crmModels, crmEnquiries, crmStatuses] = await Promise.all([
            api(form.dataset.optionsUrl),
            editing ? api(form.dataset.leadUrl) : null,
            form.dataset.companiesUrl ? api(form.dataset.companiesUrl).catch(() => null) : null,
            form.dataset.employeesUrl ? loadEmployees(form.dataset.employeesUrl).catch(() => null) : null,
            form.dataset.modelsUrl ? api(form.dataset.modelsUrl).catch(() => null) : null,
            form.dataset.enquiryTypesUrl ? api(form.dataset.enquiryTypesUrl).catch(() => null) : null,
            form.dataset.statusesUrl ? api(form.dataset.statusesUrl).catch(() => null) : null,
        ]);

        catalog = choices.data;
        crmModelsList = crmModels?.data ?? [];

        const groups = ['business_type', 'vehicle_category', 'current_vehicle', ...Array.from(choiceGroups, group => group.dataset.choices)];
        const missingGroup = groups.some(name => !options(name).length);
        const missingApplicationOptions = options('application').some(({ value }) =>
            !options('hotspot', value).length || !options('recommended_model', value).length);
        if (missingGroup || missingApplicationOptions) {
            throw new Error('Lead options are unavailable. Ask your workspace administrator to check the option catalog.');
        }

        // Fill static catalog dropdowns
        for (const name of ['business_type', 'vehicle_category', 'current_vehicle']) {
            fillSelect(form.elements[name], options(name));
        }
        for (const group of choiceGroups) renderChoices(group);

        // Fill CRM master dropdowns
        if (crmCompanies?.data?.length && crmCompany) {
            crmCompany.replaceChildren(
                new Option('Select dealer company', ''),
                ...crmCompanies.data.map(c => new Option(`${c.name} (${c.code || 'N/A'})`, c.id))
            );
        }
        if (crmEmployees?.length && assignedEmployee) {
            assignedEmployee.replaceChildren(
                new Option('Select consultant', ''),
                ...crmEmployees.map(employee => {
                    const option = new Option(employee.employee_name + ' (' + employee.emp_code + ')', employee.id);
                    option.dataset.name = employee.employee_name;
                    return option;
                })
            );
        }
        if (crmEnquiries?.data?.length && enquiryType) {
            enquiryType.replaceChildren(
                new Option('Select enquiry type', ''),
                ...crmEnquiries.data.map(t => new Option(t.name, t.id))
            );
        }
        if (crmStatuses?.data?.length && crmLeadStatus) {
            crmLeadStatus.replaceChildren(
                new Option('Select status', ''),
                ...crmStatuses.data.map(s => new Option(s.name.toUpperCase(), s.id))
            );
        }

        savedLead = lead?.data ?? null;
        populate(savedLead ?? {});
        fields.disabled = false;
        submit.disabled = false;
        reset.disabled = false;
    } catch (error) {
        showError(error, true);
    } finally {
        status.hidden = true;
        form.removeAttribute('aria-busy');
    }
}

// Event Listeners for cascading and sync
form.addEventListener('change', event => {
    const target = event.target;
    if (target.name === 'business_type') syncCompany();
    if (target.name === 'application') syncApplication(target.value);
    if (target === followUp) syncDate();

    // CRM Company -> Branches
    if (target === crmCompany) {
        loadBranches(target.value);
    }

    // CRM Branch -> set branch_name and suggest location
    if (target === crmBranch) {
        const opt = target.selectedOptions[0];
        branchName.value = opt && opt.value ? opt.text : '';
        if (opt?.value && !form.elements.location.value) {
            form.elements.location.value = opt.text;
        }
    }

    // CRM Enquiry Type -> Sources
    if (target === enquiryType) {
        loadSources(target.value);
    }

    // Recommended model -> extract vehicle_model_id and load variants
    if (target === model) {
        const opt = target.selectedOptions[0];
        const mId = opt?.dataset.modelId || '';
        vehicleModelId.value = mId;
        loadVariants(mId);
    }

    // CRM Variant -> set variant_name
    if (target === crmVariant) {
        const opt = target.selectedOptions[0];
        variantName.value = opt && opt.value ? opt.text : '';
    }

    // CRM Lead Status -> Status responses
    if (target === crmLeadStatus) {
        loadResponses(target.value);
    }

    // CRM Status Response -> set status_response text
    if (target === crmStatusResponse) {
        const opt = target.selectedOptions[0];
        statusResponseName.value = opt && opt.value ? opt.text : '';
    }
});

form.addEventListener('input', () => {
    clearErrors();
    success.hidden = true;
    submit.disabled = false;
});

form.addEventListener('reset', event => {
    event.preventDefault();
    clearErrors();
    success.hidden = true;
    submit.disabled = false;
    populate(editing ? savedLead : {});
});

form.addEventListener('submit', async event => {
    event.preventDefault();
    if (saving || fields.disabled || submit.disabled) return;
    syncDate();
    if (!form.reportValidity()) return;

    const data = Object.fromEntries(new FormData(form));
    if (crmLeadSource.disabled && enquiryType.value && savedLead?.lead_source_id &&
        String(savedLead.enquiry_type_id) === enquiryType.value) {
        data.lead_source_id = savedLead.lead_source_id;
        data.lead_source = savedLead.lead_source;
    } else {
        data.lead_source = crmLeadSource.value ? crmLeadSource.selectedOptions[0].text : data.lead_source_channel;
    }
    data.assigned_employee_name = assignedEmployee.selectedOptions[0]?.dataset.name || null;
    clearErrors();
    success.hidden = true;
    saving = true;
    fields.disabled = true;
    submit.disabled = true;
    reset.disabled = true;
    submit.textContent = 'Saving…';
    form.setAttribute('aria-busy', 'true');

    let failure;
    try {
        const result = await api(form.dataset.leadUrl, { method: editing ? 'PUT' : 'POST', data });
        savedLead = result.data;
        populate(savedLead);
    } catch (error) {
        failure = error;
    } finally {
        saving = false;
        fields.disabled = false;
        reset.disabled = false;
        submit.textContent = submitLabel;
        form.removeAttribute('aria-busy');
    }

    if (failure) {
        submit.disabled = false;
        showError(failure);
    } else {
        showSuccess(savedLead);
    }
});

retry.addEventListener('click', loadForm);
syncDate();
loadForm();
