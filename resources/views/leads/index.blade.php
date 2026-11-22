@extends('layouts.dashboard')

@section('title', 'Registered Leads')

@push('scripts')
    <script type="module" src="{{ asset('assets/lead-list.js') }}"></script>
@endpush

@section('content')
<div class="hero">
    <div>
        <div class="eyebrow">SALES CRM <span>•</span> LEAD MANAGEMENT</div>
        <h1>Registered Leads</h1>
        <p>View, edit or delete customer leads.</p>
    </div>
</div>

<main class="wrap" id="leadList"
      data-list-url="{{ route('api.leads.index') }}"
      data-orgs-url="{{ route('api.organizations.index') }}"
      data-branches-url="{{ route('api.branches.index') }}"
      data-employees-url="{{ route('api.employees.index') }}"
      data-edit-base="{{ url('/leads') }}">
    <noscript><div class="notice notice-error">Enable JavaScript to view and manage leads.</div></noscript>
    <div class="notice" id="listSuccess" role="status" hidden></div>
    <section class="card" aria-labelledby="leadListTitle">
        <div class="section-head"><div><h2 id="leadListTitle">Customer leads</h2><p id="listCount"></p></div></div>
        <div class="section-body">
            <div class="table-toolbar">
                <div class="filter-group">
                    <label for="filterOrgSelect" class="field-label" style="margin: 0;">Dealer organization:</label>
                    <select id="filterOrgSelect">
                        <option value="">All organizations</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filterBranchSelect" class="field-label" style="margin: 0;">Branch:</label>
                    <select id="filterBranchSelect" disabled>
                        <option value="">All branches</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filterEmployeeSelect" class="field-label" style="margin: 0;">Assigned employee:</label>
                    <select id="filterEmployeeSelect">
                        <option value="">All employees</option>
                        <option value="unassigned">Unassigned only</option>
                    </select>
                </div>
                <button type="button" class="btn btn-secondary" id="resetFiltersBtn" style="padding: 7px 12px;">Reset</button>
            </div>

            <p id="listStatus" role="status">Loading registered leads…</p>
            <div class="notice notice-error" id="listError" role="alert" hidden>
                <span id="listErrorText"></span>
                <button type="button" id="retryList" class="btn btn-secondary">Retry</button>
            </div>
            <table class="lead-table" id="leadTable" aria-labelledby="leadListTitle" hidden>
                <thead><tr><th scope="col">Customer</th><th scope="col">Mobile number</th><th scope="col">Branch / Dealer</th><th scope="col">Assigned employee</th><th scope="col">Recommended model</th><th scope="col">Priority</th><th scope="col">Next follow-up date</th><th scope="col">Actions</th></tr></thead>
                <tbody></tbody>
            </table>
            <nav class="pagination" id="listPagination" aria-label="Lead pages" hidden>
                <button type="button" class="btn btn-secondary" id="previousPage">Previous</button>
                <span id="currentPage"></span>
                <button type="button" class="btn btn-secondary" id="nextPage">Next</button>
            </nav>
        </div>
    </section>

    <dialog class="card lead-dialog" id="detailDialog" aria-labelledby="detailTitle">
        <div class="section-head">
            <h2 id="detailTitle">Lead details</h2>
            <form method="dialog"><button class="btn btn-secondary" autofocus>Close</button></form>
        </div>
        <div class="section-body">
            <p id="detailStatus" role="status"></p>
            <p class="notice notice-error" id="detailError" role="alert" hidden></p>
            <dl class="grid lead-details" id="leadDetails"></dl>
        </div>
    </dialog>

    <dialog class="card lead-dialog" id="assignDialog" aria-labelledby="assignTitle">
        <div class="section-head">
            <h2 id="assignTitle">Assign lead</h2>
            <button type="button" class="btn btn-secondary" id="closeAssignDialog">Close</button>
        </div>
        <div class="section-body">
            <div class="notice notice-error" id="assignError" role="alert" hidden></div>
            <form id="assignForm" class="dialog-form">
                <div class="notice notice-info" id="assignSummary"></div>
                <div class="field" style="margin-top: 15px;">
                    <label for="assignEmployeeSelect">Select sales employee <span class="req">*</span></label>
                    <select id="assignEmployeeSelect" name="employee_id" required>
                        <option value="">Select employee…</option>
                    </select>
                    <p class="field-error" id="error_employee_id" hidden></p>
                </div>
                <div class="field">
                    <label for="assignRemarks">Assignment note / remarks</label>
                    <textarea id="assignRemarks" name="remarks" rows="3" placeholder="Reason for assignment, customer preferences, or action items…"></textarea>
                    <p class="field-error" id="error_remarks" hidden></p>
                </div>
                <div class="actions" style="margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" id="cancelAssignBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="confirmAssignBtn">Save assignment</button>
                </div>
            </form>
        </div>
    </dialog>

    <dialog class="card lead-dialog" id="deleteDialog" aria-labelledby="deleteTitle" aria-describedby="deleteDescription">
        <div class="section-head"><h2 id="deleteTitle">Delete customer lead?</h2></div>
        <div class="section-body">
            <p id="deleteDescription"></p>
            <p class="notice notice-error" id="deleteError" role="alert" hidden></p>
            <div class="actions">
                <button type="button" class="btn btn-secondary" id="cancelDelete" autofocus>Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete lead</button>
            </div>
        </div>
    </dialog>
</main>
@endsection
