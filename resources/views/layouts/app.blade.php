<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ERP Conta')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-body">
    <div class="app-shell">
        <aside class="app-sidebar d-none d-lg-flex">@include('layouts.partials.sidebar')</aside>

        <div class="offcanvas offcanvas-start app-sidebar-offcanvas" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
            <div class="offcanvas-header app-sidebar-header">
                <span id="mobileSidebarLabel" class="app-brand mb-0"><i class="bi bi-calculator-fill"></i> ERP Conta</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
            </div>
            <div class="offcanvas-body p-0">@include('layouts.partials.sidebar', ['mobile' => true])</div>
        </div>

        <div class="app-main">
            @include('layouts.partials.navbar')
            <main class="app-content">
                @include('layouts.partials.breadcrumbs')
                @include('layouts.partials.alerts')
                @yield('content')
            </main>
            @include('layouts.partials.footer')
        </div>
    </div>
    @stack('scripts')
</body>
</html>
