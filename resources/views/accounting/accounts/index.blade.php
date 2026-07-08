@extends('layouts.app')

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Catálogo de cuentas</h1>
            <p class="text-muted mb-0">Estructura contable de la empresa activa.</p>
        </div>

        <a href="{{ route('accounts.create') }}" class="btn btn-primary">
            Nueva cuenta
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger">
        {{ session('error') }}
    </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            @if($accounts->count())
                @include('accounting.accounts._tree', ['accounts' => $accounts])
            @else
                <p class="text-muted mb-0">Aún no hay cuentas registradas.</p>
            @endif
        </div>
    </div>

</div>
@endsection