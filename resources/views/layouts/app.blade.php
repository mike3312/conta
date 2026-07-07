<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'ERP Conta')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-light">

<div class="d-flex min-vh-100">

    @include('layouts.partials.sidebar')

    <div class="flex-grow-1 d-flex flex-column">

        @include('layouts.partials.navbar')

        <main class="container-fluid py-4 flex-grow-1">
            @yield('content')
        </main>

        @include('layouts.partials.footer')

    </div>

</div>

</body>
</html>