@extends('layouts.dashboard')

@section('title', 'Organizations')

@push('scripts')
    <script type="module" src="{{ asset('assets/organization-list.js') }}"></script>
@endpush

@section('content')
<div class="hero">
    <div>
        <div class="eyebrow">SALES CRM <span>•</span> MASTER DATA</div>
        <h1>Organizations</h1>
        <p>Manage dealer organizations, dealer groups and distribution networks.</p>
    </div>
</div>

<main class="wrap" id="orgList" data-api-url="{{ route('api.organizations.index') }}">
    <noscript><div class="notice notice-error">Enable JavaScript to view and manage organizations.</div></noscript>
    <div class="notice" id="listSuccess" role="status" hidden></div>

    <section class="card" aria-labelledby="orgListTitle">
        <div class="section-head" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 id="orgListTitle">Dealer organizations</h2>
                <p id="listCount"></p>
            </div>
            <button type="button" class="btn btn-primary" id="openCreateBtn">+ Add organization</button>
        </div>

        <div class="section-body">
            <div class="table-toolbar">
                <div class="filter-group">
                    <label for="statusFilter" class="field-label" style="margin: 0;">Status filter:</label>
                    <select id="statusFilter">
                        <option value="">All statuses</option>
                        <option value="1">Active only</option>
                        <option value="0">Inactive only</option>
                    </select>
                </div>
            </div>

            <p id="listStatus" role="status">Loading organizations…</p>
            <div class="notice notice-error" id="listError" role="alert" hidden>
                <span id="listErrorText"></span>
                <button type="button" id="retryList" class="btn btn-secondary">Retry</button>
            </div>

            <table class="lead-table" id="orgTable" aria-labelledby="orgListTitle" hidden>
                <thead>
                    <tr>
                        <th scope="col">Code</th>
                        <th scope="col">Organization Name</th>
                        <th scope="col">Type</th>
                        <th scope="col">Branches</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

            <nav class="pagination" id="listPagination" aria-label="Organization pages" hidden>
                <button type="button" class="btn btn-secondary" id="previousPage">Previous</button>
                <span id="currentPage"></span>
                <button type="button" class="btn btn-secondary" id="nextPage">Next</button>
            </nav>
        </div>
    </section>

    <!-- Create / Edit Dialog -->
    <dialog class="card lead-dialog" id="orgDialog" aria-labelledby="orgDialogTitle">
        <div class="section-head">
            <h2 id="orgDialogTitle">New organization</h2>
            <button type="button" class="btn btn-secondary" id="closeOrgDialog">Close</button>
        </div>
        <div class="section-body">
            <div class="notice notice-error" id="orgFormError" role="alert" hidden></div>
            <form id="orgForm" class="dialog-form">
                <div class="field">
                    <label for="orgCode">Organization code <span class="req">*</span></label>
                    <input type="text" id="orgCode" name="organization_code" maxlength="50" required placeholder="e.g. ORG001">
                    <p class="field-error" id="error_organization_code" hidden></p>
                </div>
                <div class="field">
                    <label for="orgName">Organization name <span class="req">*</span></label>
                    <input type="text" id="orgName" name="organization_name" maxlength="150" required placeholder="e.g. Mahindra Automotive Hub">
                    <p class="field-error" id="error_organization_name" hidden></p>
                </div>
                <div class="field">
                    <label for="orgType">Organization type</label>
                    <input type="text" id="orgType" name="organization_type" maxlength="100" placeholder="e.g. Dealer, Distributor, Fleet Partner">
                    <p class="field-error" id="error_organization_type" hidden></p>
                </div>
                <div class="field">
                    <label class="checkbox-label" for="orgActive">
                        <input type="checkbox" id="orgActive" name="is_active" checked>
                        <span>Active record (available in branch and lead assignment)</span>
                    </label>
                </div>
                <div class="actions" style="margin-top: 15px;">
                    <button type="button" class="btn btn-secondary" id="cancelOrgForm">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveOrgBtn">Save organization</button>
                </div>
            </form>
        </div>
    </dialog>

    <!-- Delete Confirmation Dialog -->
    <dialog class="card lead-dialog" id="deleteOrgDialog" aria-labelledby="deleteOrgTitle">
        <div class="section-head"><h2 id="deleteOrgTitle">Delete organization?</h2></div>
        <div class="section-body">
            <p id="deleteOrgDescription"></p>
            <div class="notice notice-error" id="deleteOrgError" role="alert" hidden></div>
            <div class="actions">
                <button type="button" class="btn btn-secondary" id="cancelDeleteOrg">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteOrg">Delete organization</button>
            </div>
        </div>
    </dialog>
</main>
@endsection
