@extends('layouts.dashboard')

@section('title', $lead ? 'Edit Customer Lead' : 'New Customer Registration')

@push('scripts')
    <script type="module" src="{{ asset('assets/lead-form.js') }}"></script>
@endpush

@section('content')
<div class="hero">
    <div>
        <div class="eyebrow">SALES CRM <span>•</span> LEAD CAPTURE</div>
        <h1>{{ $lead ? 'Edit Customer Lead' : 'New Customer Registration' }}</h1>
        <p>{{ $lead ? 'Update the customer details, vehicle requirement or next action.' : 'Register a prospect, understand the vehicle requirement and plan the next action.' }}</p>
    </div>
    <div class="hero-actions"><span class="pill">● {{ $lead ? 'Editing lead #'.$lead->id : 'New lead entry' }}</span><span class="today-label" id="todayLabel"></span></div>
</div>

<main class="wrap">
    <div class="dashboard-strip" id="dashboard">
        <div class="metric"><span class="metric-icon blue">＋</span><div><small>Lead capture</small><b>New prospect</b><em>Register customer details</em></div></div>
        <div class="metric"><span class="metric-icon teal">⌖</span><div><small>Market approach</small><b>Hotspot mapping</b><em>Passenger &amp; cargo segments</em></div></div>
        <div class="metric"><span class="metric-icon violet">↗</span><div><small>Sales priority</small><b>Follow-up focused</b><em>HOT · WARM · COLD leads</em></div></div>
    </div>
    <div class="progress" aria-hidden="true"><span></span></div>
    <noscript><div class="notice notice-error">Enable JavaScript to load and submit the lead form.</div></noscript>
    <p class="notice notice-info" id="formStatus" role="status">Loading lead form…</p>
    <div class="notice notice-error" id="formError" role="alert" tabindex="-1" hidden>
        <span id="formErrorText"></span>
        <button type="button" class="btn btn-secondary" id="retryForm" hidden>Retry</button>
    </div>

    <form id="leadForm" method="post"
          data-options-url="{{ route('api.lead-options') }}"
          data-orgs-url="{{ route('api.organizations.index') }}"
          data-branches-url="{{ route('api.branches.index') }}"
          data-employees-url="{{ route('api.employees.index') }}"
          data-lead-url="{{ $lead ? route('api.leads.show', $lead) : route('api.leads.store') }}"
          data-editing="{{ $lead ? 'true' : 'false' }}">
        <fieldset class="form-fields" id="formFields" aria-label="Customer lead details" disabled>
            <section class="card" aria-labelledby="leadInformation">
                <div class="section-head"><div class="step">01</div><div><h2 id="leadInformation">Lead information</h2><p>Identify the customer, dealer network and how the opportunity was generated.</p></div></div>
                <div class="section-body"><div class="grid">
                    <div class="field full">
                        <span class="field-label" id="leadSourceLabel">Lead source <span class="req">*</span></span>
                        <div class="source-grid" role="radiogroup" aria-labelledby="leadSourceLabel" aria-describedby="leadSourceHint" data-choices="lead_source" data-required></div>
                        <div class="hint" id="leadSourceHint">Select the channel through which this lead was received.</div>
                    </div>
                    <div class="field">
                        <label for="organizationId">Dealer organization</label>
                        <select id="organizationId" name="organization_id">
                            <option value="">Select organization (Optional)</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="branchId">Branch location</label>
                        <select id="branchId" name="branch_id" disabled>
                            <option value="">Select organization first</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="assignedEmployeeId">Assigned sales employee</label>
                        <select id="assignedEmployeeId" name="assigned_employee_id" disabled>
                            <option value="">Select branch first</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="businessType">Business type <span class="req">*</span></label>
                        <select id="businessType" name="business_type" required><option value="">Select business type</option></select>
                    </div>
                    <div class="field company-field hidden">
                        <label for="companyName">Company name <span class="req">*</span></label>
                        <input name="company_name" id="companyName" maxlength="255" placeholder="Enter company / firm name" disabled>
                    </div>
                    <div class="field">
                        <label for="location">Company / customer location <span class="req">*</span></label>
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
                <div class="section-head"><div class="step">02</div><div><h2 id="vehicleRequirement">Vehicle requirement</h2><p>Understand the application and identify the right Mahindra product.</p></div></div>
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
                <div class="section-head"><div class="step">03</div><div><h2 id="purchaseTimeline">Purchase timeline &amp; follow-up</h2><p>Prioritise the lead and plan the next customer interaction.</p></div></div>
                <div class="section-body"><div class="grid">
                    <div class="field full">
                        <span class="field-label" id="priorityLabel">Purchase timeline <span class="req">*</span></span>
                        <div class="timeline" role="radiogroup" aria-labelledby="priorityLabel" data-choices="priority" data-required></div>
                    </div>
                    <div class="field">
                        <label for="followUp">Next follow-up date <span class="req">*</span></label>
                        <input type="date" name="follow_up_date" id="followUp" max="9999-12-31" required>
                    </div>
                    <div class="field span2">
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
</main>
@endsection
