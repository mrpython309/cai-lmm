<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="workspace-timezone" content="{{ config('app.timezone') }}">
    <title>CAI LMM | @yield('title')</title>
    <link rel="stylesheet" href="{{ asset('assets/dashboard.css') }}?v={{ filemtime(public_path('assets/dashboard.css')) }}">
    @stack('scripts')
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <div class="brand">
            <span class="brand-mark" aria-hidden="true">CAI</span>
            <span>CAI LMM <small>Sales Workspace</small></span>
        </div>
        @auth
            <nav class="workspace-nav" aria-label="Workspace">
                <a href="{{ route('leads.create') }}" @if (request()->routeIs('leads.create', 'leads.edit')) aria-current="page" @endif>New lead</a>
                <a href="{{ route('leads.index') }}" @if (request()->routeIs('leads.index')) aria-current="page" @endif>Registered leads</a>
            </nav>
            <form class="sign-out" method="post" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-secondary" type="submit">Sign out</button>
            </form>
        @endauth
    </div>
</header>
@yield('content')
</body>
</html>
