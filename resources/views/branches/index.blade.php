@extends('layouts.dashboard')

@section('title', 'Branches')

@push('scripts')
    <script type="module" src="{{ asset('assets/branch-list.js') }}"></script>
@endpush

@section('content')
<div class="hero">
    <div>
        <div class="eyebrow">SALES CRM <span>•</span> MASTER DATA</div>
        <h1>Branches</h1>
        <p>Manage branch dealerships, showrooms and service hubs by organization.</p>
    </div>
</div>

<main class="wrap" id="branchList" data-api-url="{{ route('api.branches.index') }}" data-orgs-url="{{ route('api.organizations.index') }}">
    <noscript><div class="notice notice-error">Enable JavaScript to view and manage branches.</div></noscript>
    <div class="notice" id="listSuccess" role="status" hidden></div>

    <!-- Dynamic Organization & Branch Cascading Explorer / Filter -->
    <section class="card" aria-labelledby="filterTitle">
        <div class="section-head"><div class="step">⌖</div><div><h2 id="filterTitle">Dynamic Organization &amp; Branch Lookup</h2><p>Filter active branches by selecting a dealer organization.</p></div></div>
        <div class="section-body">
            <div class="grid">
                <div class="field">
                    <label for="filterOrgSelect">Filter by organization (Active only)</label>
                    <select id="filterOrgSelect">
                        <option value="">All organizations</option>
                    </select>
                </div>
                <div class="field">
                    <label for="cascadingBranchPicker">Active branches in selected organization</label>
                    <select id="cascadingBranchPicker" disabled>
                        <option value="">Select organization first</option>
                    </select>
                </div>
                <div class="field" style="display: flex; align-items: flex-end; gap: 8px;">
                    <button type="button" class="btn btn-secondary" id="resetFilterBtn">Reset filters</button>
                    <button type="button" class="btn btn-primary" id="openCreateBtn" style="margin-left: auto;">+ Add branch</button>
                </div>
            </div>
        </div>
    </section>

    <!-- Branch List Table -->
    <section class="card" aria-labelledby="branchListTitle">
        <div class="section-head" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 id="branchListTitle">Dealer branches</h2>
                <p id="listCount"></p>
            </div>
        </div>

        <div class="section-body">
            <p id="listStatus" role="status">Loading branches…</p>
            <div class="notice notice-error" id="listError" role="alert" hidden>
                <span id="listErrorText"></span>
                <button type="button" id="retryList" class="btn btn-secondary">Retry</button>
            </div>

            <table class="lead-table" id="branchTable" aria-labelledby="branchListTitle" hidden>
                <thead>
                    <tr>
                        <th scope="col">Branch Code</th>
                        <th scope="col">Branch Name</th>
                        <th scope="col">Organization</th>
                        <th scope="col">City / State</th>
                        <th scope="col">Contact</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

            <nav class="pagination" id="listPagination" aria-label="Branch pages" hidden>
                <button type="button" class="btn btn-secondary" id="previousPage">Previous</button>
                <span id="currentPage"></span>
                <button type="button" class="btn btn-secondary" id="nextPage">Next</button>
            </nav>
        </div>
    </section>

    <!-- Create / Edit Dialog -->
    <dialog class="card lead-dialog" id="branchDialog" aria-labelledby="branchDialogTitle">
        <div class="section-head">
            <h2 id="branchDialogTitle">New branch</h2>
            <button type="button" class="btn btn-secondary" id="closeBranchDialog">Close</button>
        </div>
        <div class="section-body">
            <div class="notice notice-error" id="branchFormError" role="alert" hidden></div>
            <form id="branchForm" class="dialog-form">
                <div class="field">
                    <label for="branchOrg">Organization <span class="req">*</span></label>
                    <select id="branchOrg" name="organization_id" required>
                        <option value="">Select organization (Active only)</option>
                    </select>
                    <p class="field-error" id="error_organization_id" hidden></p>
                </div>
                <div class="grid">
                    <div class="field">
                        <label for="branchCode">Branch code <span class="req">*</span></label>
                        <input type="text" id="branchCode" name="branch_code" maxlength="50" required placeholder="e.g. BR001">
                        <p class="field-error" id="error_branch_code" hidden></p>
                    </div>
                    <div class="field span2">
                        <label for="branchName">Branch name <span class="req">*</span></label>
                        <input type="text" id="branchName" name="branch_name" maxlength="150" required placeholder="e.g. Chennai Central Showroom">
                        <p class="field-error" id="error_branch_name" hidden></p>
                    </div>
                </div>
                <div class="field">
                    <label for="branchAddress">Address</label>
                    <input type="text" id="branchAddress" name="address" maxlength="255" placeholder="Street / Industrial Area">
                    <p class="field-error" id="error_address" hidden></p>
                </div>
                <div class="grid">
                    <div class="field">
                        <label for="branchCity">City</label>
                        <input type="text" id="branchCity" name="city" maxlength="100" placeholder="e.g. Chennai">
                    </div>
                    <div class="field">
                        <label for="branchState">State</label>
                        <input type="text" id="branchState" name="state" maxlength="100" placeholder="e.g. Tamil Nadu">
                    </div>
                    <div class="field">
                        <label for="branchPincode">Pincode</label>
                        <input type="text" id="branchPincode" name="pincode" maxlength="10" placeholder="e.g. 600001">
                    </div>
                </div>
                <div class="grid">
                    <div class="field">
                        <label for="branchContactPerson">Contact person</label>
                        <input type="text" id="branchContactPerson" name="contact_person" maxlength="150" placeholder="e.g. Ramesh Kumar">
                    </div>
                    <div class="field">
                        <label for="branchContactNumber">Contact number</label>
                        <input type="text" id="branchContactNumber" name="contact_number" maxlength="15" placeholder="e.g. 9876543210">
                    </div>
                    <div class="field">
                        <label for="branchEmail">Email address</label>
                        <input type="email" id="branchEmail" name="email" maxlength="254" placeholder="branch@example.com">
                    </div>
                </div>
                <div class="field">
                    <label class="checkbox-label" for="branchActive">
                        <input type="checkbox" id="branchActive" name="is_active" checked>
                        <span>Active branch (available for lead assignment and operations)</span>
                    </label>
                </div>
                <div class="actions" style="margin-top: 15px;">
                    <button type="button" class="btn btn-secondary" id="cancelBranchForm">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveBranchBtn">Save branch</button>
                </div>
            </form>
        </div>
    </dialog>

    <!-- Delete Confirmation Dialog -->
    <dialog class="card lead-dialog" id="deleteBranchDialog" aria-labelledby="deleteBranchTitle">
        <div class="section-head"><h2 id="deleteBranchTitle">Delete branch?</h2></div>
        <div class="section-body">
            <p id="deleteBranchDescription"></p>
            <div class="notice notice-error" id="deleteBranchError" role="alert" hidden></div>
            <div class="actions">
                <button type="button" class="btn btn-secondary" id="cancelDeleteBranch">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBranch">Delete branch</button>
            </div>
        </div>
    </dialog>
</main>
@endsection
