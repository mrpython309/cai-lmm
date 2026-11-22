<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="workspace-timezone" content="{{ config('app.timezone') }}">
    <title>CAI LMM | @yield('title')</title>
    <link rel="stylesheet" href="{{ asset('assets/dashboard.css') }}">
    @stack('scripts')
</head>
<body>
<div class="app-shell">
    <div class="main-panel single-page">
        <header class="topbar">
            <div class="mobile-brand"><div class="logo">CAI</div><b>CAI LMM BUSINESS HUB</b></div>
            <div class="topbar-left"><b>Sales Dashboard &amp; Customer Registration</b></div>
            <div class="topbar-tools">
                @auth
                    <nav class="workspace-nav" aria-label="Workspace">
                        <a class="btn btn-secondary" href="{{ route('leads.create') }}">New lead</a>
                        <a class="btn btn-secondary" href="{{ route('leads.index') }}">Registered leads</a>
                        <a class="btn btn-secondary" href="{{ route('organizations.index') }}">Organizations</a>
                        <a class="btn btn-secondary" href="{{ route('branches.index') }}">Branches</a>
                        <a class="btn btn-secondary" href="{{ route('employees.index') }}">Employees</a>
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn btn-secondary" type="submit">Sign out</button>
                        </form>
                    </nav>
                @endauth
                <div class="topbar-right"><span class="online-dot"></span><span>CAI Sales Workspace</span><div class="top-avatar">CA</div></div>
            </div>
        </header>
        @yield('content')
    </div>
</div>
</body>
</html>
