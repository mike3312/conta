<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'ERP Conta') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="guest-body">
    <main class="guest-shell">
        <div class="w-100" style="max-width: 440px">
            <a href="/" class="app-brand guest-brand"><span class="brand-mark"><i class="bi bi-calculator-fill"></i></span><span>ERP Conta</span></a>
            <section class="app-card guest-card">{{ $slot }}</section>
            <p class="text-center text-muted small mt-3">Contabilidad clara para empresas en crecimiento.</p>
        </div>
    </main>
</body>
</html>
