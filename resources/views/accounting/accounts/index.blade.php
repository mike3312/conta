@extends('layouts.app')

@section('content')
<div class="container py-4">

    <x-page-header title="Catálogo de cuentas" subtitle="Estructura contable de la empresa activa." icon="bi-diagram-3"><x-slot:actions><x-action-button :href="route('accounts.create')" icon="bi-plus-lg">Nueva cuenta</x-action-button></x-slot:actions></x-page-header>

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
