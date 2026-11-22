@extends('layouts.dashboard')

@section('title', 'Employees')

@push('scripts')
    <script type="module" src="{{ asset('assets/employee-list.js') }}"></script>
@endpush

@section('content')
<div class="hero">
    <div>
        <div class="eyebrow">SALES CRM <span>•</span> MASTER DATA</div>
        <h1>Employees</h1>
        <p>Manage dealership personnel, sales consultants, and branch staff.</p>
    </div>
</div>

<main class="wrap" id="employeeList"
      data-api-url="{{ route('api.employees.index') }}"
      data-orgs-url="{{ route('api.organizations.index') }}"
      data-branches-url="{{ route('api.branches.index') }}">
    <noscript><div class="notice notice-error">Enable JavaScript to view and manage employees.</div></noscript>
    <div class="notice" id="listSuccess" role="status" hidden></div>

    <!-- Dynamic Organization & Branch Cascading Explorer & Search Filter -->
    <section class="card" aria-labelledby="filterTitle">
        <div class="section-head">
            <div class="step">⌖</div>
            <div>
                <h2 id="filterTitle">Filter &amp; Search Employees</h2>
                <p>Filter by dealer organization, branch, status, or search by keyword.</p>
            </div>
        </div>
        <div class="section-body">
            <div class="grid">
                <div class="field">
                    <label for="filterOrgSelect">Organization</label>
                    <select id="filterOrgSelect">
                        <option value="">All organizations</option>
                    </select>
                </div>
                <div class="field">
                    <label for="filterBranchSelect">Branch</label>
                    <select id="filterBranchSelect" disabled>
                        <option value="">Select organization first</option>
                    </select>
                </div>
                <div class="field">
                    <label for="searchFilter">Keyword search</label>
                    <input type="text" id="searchFilter" placeholder="Name, ID, email, designation…">
                </div>
                <div class="field">
                    <label for="statusFilter">Status filter</label>
                    <select id="statusFilter">
                        <option value="">All statuses</option>
                        <option value="1">Active only</option>
                        <option value="0">Inactive only</option>
                    </select>
                </div>
            </div>
            <div class="actions" style="margin-top: 15px; display: flex; justify-content: space-between;">
                <button type="button" class="btn btn-secondary" id="resetFilterBtn">Reset filters</button>
                <button type="button" class="btn btn-primary" id="openCreateBtn">+ Add employee</button>
            </div>
        </div>
    </section>

    <!-- Employee List Table -->
    <section class="card" aria-labelledby="employeeListTitle">
        <div class="section-head" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 id="employeeListTitle">Employees directory</h2>
                <p id="listCount"></p>
            </div>
        </div>

        <div class="section-body">
            <p id="listStatus" role="status">Loading employees…</p>
            <div class="notice notice-error" id="listError" role="alert" hidden>
                <span id="listErrorText"></span>
                <button type="button" id="retryList" class="btn btn-secondary">Retry</button>
            </div>

            <table class="lead-table" id="employeeTable" aria-labelledby="employeeListTitle" hidden>
                <thead>
                    <tr>
                        <th scope="col">Employee ID</th>
                        <th scope="col">Name</th>
                        <th scope="col">Designation</th>
                        <th scope="col">Organization</th>
                        <th scope="col">Branch</th>
                        <th scope="col">Contact</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

            <nav class="pagination" id="listPagination" aria-label="Employee pages" hidden>
                <button type="button" class="btn btn-secondary" id="previousPage">Previous</button>
                <span id="currentPage"></span>
                <button type="button" class="btn btn-secondary" id="nextPage">Next</button>
            </nav>
        </div>
    </section>

    <!-- Create / Edit Dialog -->
    <dialog class="card lead-dialog" id="employeeDialog" aria-labelledby="employeeDialogTitle">
        <div class="section-head">
            <h2 id="employeeDialogTitle">New employee</h2>
            <button type="button" class="btn btn-secondary" id="closeEmployeeDialog">Close</button>
        </div>
        <div class="section-body">
            <div class="notice notice-error" id="employeeFormError" role="alert" hidden></div>
            <form id="employeeForm" class="dialog-form">
                <div class="grid">
                    <div class="field">
                        <label for="employeeOrg">Organization <span class="req">*</span></label>
                        <select id="employeeOrg" name="organization_id" required>
                            <option value="">Select organization (Active only)</option>
                        </select>
                        <p class="field-error" id="error_organization_id" hidden></p>
                    </div>
                    <div class="field">
                        <label for="employeeBranch">Branch <span class="req">*</span></label>
                        <select id="employeeBranch" name="branch_id" required disabled>
                            <option value="">Select organization first</option>
                        </select>
                        <p class="field-error" id="error_branch_id" hidden></p>
                    </div>
                </div>

                <div class="grid">
                    <div class="field">
                        <label for="employeeId">Employee ID <span class="req">*</span></label>
                        <input type="text" id="employeeId" name="employee_id" maxlength="50" required placeholder="e.g. EMP001">
                        <p class="field-error" id="error_employee_id" hidden></p>
                    </div>
                    <div class="field span2">
                        <label for="employeeName">Full name <span class="req">*</span></label>
                        <input type="text" id="employeeName" name="name" maxlength="150" required placeholder="e.g. Rahul Sharma">
                        <p class="field-error" id="error_name" hidden></p>
                    </div>
                </div>

                <div class="grid">
                    <div class="field">
                        <label for="employeeDesignation">Designation <span class="req">*</span></label>
                        <input type="text" id="employeeDesignation" name="designation" maxlength="100" required placeholder="e.g. Sales Executive, Branch Manager">
                        <p class="field-error" id="error_designation" hidden></p>
                    </div>
                    <div class="field">
                        <label for="employeeMobile">Mobile number <span class="req">*</span></label>
                        <input type="tel" id="employeeMobile" name="mobile_number" maxlength="20" required placeholder="e.g. 9876543210">
                        <p class="field-error" id="error_mobile_number" hidden></p>
                    </div>
                    <div class="field">
                        <label for="employeeEmail">Email address <span class="req">*</span></label>
                        <input type="email" id="employeeEmail" name="email" maxlength="254" required placeholder="e.g. rahul@caimahindra.com">
                        <p class="field-error" id="error_email" hidden></p>
                    </div>
                </div>

                <div class="field" style="margin-top: 10px;">
                    <label class="checkbox-label" for="employeeActive">
                        <input type="checkbox" id="employeeActive" name="is_active" checked>
                        <span>Active employee (available for assignment and operations)</span>
                    </label>
                </div>

                <div class="actions" style="margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" id="cancelEmployeeForm">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveEmployeeBtn">Save employee</button>
                </div>
            </form>
        </div>
    </dialog>

    <!-- Delete Confirmation Dialog -->
    <dialog class="card lead-dialog" id="deleteEmployeeDialog" aria-labelledby="deleteEmployeeTitle">
        <div class="section-head"><h2 id="deleteEmployeeTitle">Delete employee?</h2></div>
        <div class="section-body">
            <p id="deleteEmployeeDescription"></p>
            <div class="notice notice-error" id="deleteEmployeeError" role="alert" hidden></div>
            <div class="actions">
                <button type="button" class="btn btn-secondary" id="cancelDeleteEmployee">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteEmployee">Delete employee</button>
            </div>
        </div>
    </dialog>
</main>
@endsection
