@extends('layouts.dashboard')

@section('title', $lead ? 'Edit Customer Lead' : 'New Customer Registration')

@push('scripts')
    <script type="module" src="{{ asset('assets/lead-form.js') }}"></script>
@endpush

@section('content')
<div class="hero">
    <div>
        <h1>{{ $lead ? 'Edit Customer Lead' : 'New Customer Registration' }}</h1>
        <p>{{ $lead ? 'Update the customer details, vehicle requirement or next action.' : 'Register a prospect, understand the vehicle requirement and plan the next action.' }}</p>
    </div>
    <div class="hero-actions">
        @if ($lead)
            <span>Lead #{{ $lead->id }}</span>
        @endif
        <time id="todayLabel"></time>
    </div>
</div>

<main class="wrap registration-layout">
    <nav class="section-nav" aria-label="Registration sections">
        <strong>Jump to</strong>
        <a href="#leadInformation">Lead information</a>
        <a href="#vehicleRequirement">Vehicle requirement</a>
        <a href="#purchaseTimeline">Follow-up</a>
    </nav>

    <div class="registration-content">
        <noscript><div class="notice notice-error">Enable JavaScript to load and submit the lead form.</div></noscript>
        <p class="notice notice-info" id="formStatus" role="status">Loading lead form…</p>
        <div class="notice notice-error" id="formError" role="alert" tabindex="-1" hidden>
            <span id="formErrorText"></span>
            <button type="button" class="btn btn-secondary" id="retryForm" hidden>Retry</button>
        </div>

        <form id="leadForm" method="post"
              data-options-url="{{ route('api.lead-options') }}"
              data-lead-url="{{ $lead ? route('api.leads.show', $lead) : route('api.leads.store') }}"
              data-companies-url="{{ route('api.mahindra.companies') }}"
              data-employees-url="{{ route('api.mahindra.employees') }}"
              data-models-url="{{ route('api.mahindra.vehicle-models') }}"
              data-enquiry-types-url="{{ route('api.mahindra.enquiry-types') }}"
              data-statuses-url="{{ route('api.mahindra.lead-statuses') }}"
              data-editing="{{ $lead ? 'true' : 'false' }}">
            <fieldset class="form-fields" id="formFields" aria-label="Customer lead details" disabled>
                <section class="card" aria-labelledby="leadInformation">
                    <div class="section-head"><div><h2 id="leadInformation" tabindex="-1">Lead information</h2><p>Identify the customer, branch, and how the opportunity was generated.</p></div></div>
                    <div class="section-body"><div class="grid">
                        <div class="field">
                            <label for="enquiryType">Enquiry type (CRM)</label>
                            <select id="enquiryType" name="enquiry_type_id">
                                <option value="">Select enquiry type</option>
                            </select>
                            <input type="hidden" name="enquiry_type" id="enquiryTypeName">
                        </div>
                        <div class="field">
                            <label for="crmLeadSource">Enquiry source (CRM)</label>
                            <select id="crmLeadSource" name="lead_source_id" disabled>
                                <option value="">Select enquiry type first</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="crmCompany">Dealer organization (CRM)</label>
                            <select id="crmCompany" name="company_id">
                                <option value="">Select dealer company</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="crmBranch">Dealer branch (CRM)</label>
                            <select id="crmBranch" name="branch_id" disabled>
                                <option value="">Select company first</option>
                            </select>
                            <input type="hidden" name="branch_name" id="branchName">
                        </div>
                        <div class="field">
                            <label for="assignedEmployee">Assigned consultant (CRM)</label>
                            <select id="assignedEmployee" name="assigned_employee_id">
                                <option value="">Select consultant</option>
                            </select>
                        </div>
                        <div class="field full">
                            <span class="field-label" id="leadSourceLabel">Lead source channel <span class="req">*</span></span>
                            <div class="source-grid" role="radiogroup" aria-labelledby="leadSourceLabel" aria-describedby="leadSourceHint" data-choices="lead_source" data-input="lead_source_channel" data-required></div>
                            <div class="hint" id="leadSourceHint">Select the primary channel through which this lead was received.</div>
                        </div>
                        <div class="field">
                            <label for="businessType">Business type <span class="req">*</span></label>
                            <select id="businessType" name="business_type" required><option value="">Select business type</option></select>
                        </div>
                        <div class="field company-field hidden">
                            <label for="companyName">Customer company name <span class="req">*</span></label>
                            <input name="company_name" id="companyName" maxlength="255" placeholder="Enter company / firm name" disabled>
                        </div>
                        <div class="field">
                            <label for="location">Customer location / area <span class="req">*</span></label>
                            <input id="location" name="location" maxlength="255" required placeholder="Area, city or business location">
                        </div>
                        <div class="field">
                            <label for="customerName">Customer name <span class="req">*</span></label>
                            <input id="customerName" name="customer_name" maxlength="255" required placeholder="Enter full name">
                        </div>
                        <div class="field">
                            <label for="mobile">Mobile number <span class="req">*</span></label>
                            <input id="mobile" name="mobile" required inputmode="numeric" maxlength="10" pattern="[6-9][0-9]{9}" title="Enter a 10-digit mobile number starting with 6, 7, 8, or 9." placeholder="10-digit mobile number">
                        </div>
                        <div class="field">
                            <label for="email">Email address</label>
                            <input id="email" type="email" name="email" maxlength="254" placeholder="name@example.com">
                        </div>
                    </div></div>
                </section>

                <section class="card" aria-labelledby="vehicleRequirement">
                    <div class="section-head"><div><h2 id="vehicleRequirement" tabindex="-1">Vehicle requirement</h2><p>Understand the application and identify the right Mahindra product.</p></div></div>
                    <div class="section-body"><div class="grid">
                        <div class="field full">
                            <span class="field-label" id="applicationLabel">Vehicle application <span class="req">*</span></span>
                            <div class="choice-row" role="radiogroup" aria-labelledby="applicationLabel" data-choices="application" data-required></div>
                        </div>
                        <div class="field">
                            <label for="vehicleCategory">Vehicle category <span class="req">*</span></label>
                            <select id="vehicleCategory" name="vehicle_category" required><option value="">Select category</option></select>
                        </div>
                        <div class="field full hidden" id="hotspotField">
                            <label for="hotspot"><span id="hotspotLabel">Passenger hotspots</span> <span class="req">*</span></label>
                            <select name="hotspot" id="hotspot" aria-describedby="hotspotHint" disabled><option value="">Select hotspot</option></select>
                            <div class="hint" id="hotspotHint">Choose the main customer operating / business hotspot.</div>
                        </div>
                        <div class="field">
                            <label for="currentVehicle">Current vehicle</label>
                            <select id="currentVehicle" name="current_vehicle"><option value="">Select current vehicle</option></select>
                        </div>
                        <div class="field">
                            <span class="field-label" id="exchangeLabel">Exchange interested?</span>
                            <div class="choice-row" role="radiogroup" aria-labelledby="exchangeLabel" data-choices="exchange"></div>
                        </div>
                        <div class="field">
                            <label for="model">Recommended Mahindra model <span class="req">*</span></label>
                            <select name="recommended_model" id="model" required><option value="">Select application first</option></select>
                            <input type="hidden" name="vehicle_model_id" id="vehicleModelId">
                        </div>
                        <div class="field">
                            <label for="crmVariant">Model variant (CRM)</label>
                            <select name="variant_id" id="crmVariant" disabled>
                                <option value="">Select model first</option>
                            </select>
                            <input type="hidden" name="variant_name" id="variantName">
                        </div>
                        <div class="field">
                            <span class="field-label" id="testDriveLabel">Test drive required?</span>
                            <div class="choice-row" role="radiogroup" aria-labelledby="testDriveLabel" data-choices="test_drive"></div>
                        </div>
                        <div class="field">
                            <span class="field-label" id="financeLabel">Finance required?</span>
                            <div class="choice-row" role="radiogroup" aria-labelledby="financeLabel" data-choices="finance"></div>
                        </div>
                    </div></div>
                </section>

                <section class="card" aria-labelledby="purchaseTimeline">
                    <div class="section-head"><div><h2 id="purchaseTimeline" tabindex="-1">Purchase timeline &amp; follow-up</h2><p>Prioritise the lead and plan the next customer interaction.</p></div></div>
                    <div class="section-body"><div class="grid">
                        <div class="field full">
                            <span class="field-label" id="priorityLabel">Purchase timeline <span class="req">*</span></span>
                            <div class="timeline" role="radiogroup" aria-labelledby="priorityLabel" data-choices="priority" data-required></div>
                        </div>
                        <div class="field">
                            <label for="crmLeadStatus">Lead status (CRM)</label>
                            <select name="lead_status_id" id="crmLeadStatus">
                                <option value="">Select status</option>
                            </select>
                            <input type="hidden" name="lead_status" id="leadStatus">
                        </div>
                        <div class="field">
                            <label for="crmStatusResponse">Status response (CRM)</label>
                            <select name="status_response_id" id="crmStatusResponse" disabled>
                                <option value="">Select status first</option>
                            </select>
                            <input type="hidden" name="status_response" id="statusResponse">
                        </div>
                        <div class="field">
                            <label for="followUp">Next follow-up date <span class="req">*</span></label>
                            <input type="date" name="follow_up_date" id="followUp" max="9999-12-31" required>
                        </div>
                        <div class="field full">
                            <label for="remarks">Remarks / customer requirement</label>
                            <textarea id="remarks" name="remarks" maxlength="5000" placeholder="Enter business requirement, expected quantity, route / usage details, objections or other notes..."></textarea>
                        </div>
                    </div></div>
                </section>
            </fieldset>

            <div class="footer">
                <div class="footer-note">Fields marked <span class="req">*</span> are mandatory.</div>
                <div class="actions">
                    <button type="reset" class="btn btn-secondary" disabled>{{ $lead ? 'Reset changes' : 'Clear form' }}</button>
                    <button type="submit" class="btn btn-primary" disabled>{{ $lead ? 'Save changes ↗' : 'Submit lead details ↗' }}</button>
                </div>
            </div>
            <div class="notice" id="success" role="status" tabindex="-1" hidden></div>
        </form>
    </div>
</main>
@endsection
