@extends('layouts.dashboard')

@section('title', 'Registered Leads')

@push('scripts')
    <script type="module" src="{{ asset('assets/lead-list.js') }}?v={{ filemtime(public_path('assets/lead-list.js')) }}"></script>
@endpush

@section('content')
<div class="hero">
    <div>
        <h1>Registered Leads</h1>
        <p>View, edit or delete customer leads.</p>
    </div>
</div>

<main class="wrap" id="leadList" data-list-url="{{ route('api.leads.index') }}" data-edit-base="{{ url('/leads') }}">
    <noscript><div class="notice notice-error">Enable JavaScript to view and manage leads.</div></noscript>
    <div class="notice" id="listSuccess" role="status" hidden></div>
    <section class="card" aria-labelledby="leadListTitle">
        <div class="section-head"><div><h2 id="leadListTitle">Customer leads</h2><p id="listCount"></p></div></div>
        <div class="section-body">
            <p id="listStatus" role="status">Loading registered leads…</p>
            <div class="notice notice-error" id="listError" role="alert" hidden>
                <span id="listErrorText"></span>
                <button type="button" id="retryList" class="btn btn-secondary">Retry</button>
            </div>
            <table class="lead-table" id="leadTable" aria-labelledby="leadListTitle" hidden>
                <thead><tr><th scope="col">Customer</th><th scope="col">Mobile number</th><th scope="col">Recommended model</th><th scope="col">Priority</th><th scope="col">Next follow-up date</th><th scope="col">Actions</th></tr></thead>
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
