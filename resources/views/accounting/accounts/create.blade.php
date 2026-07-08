@extends('layouts.app')

@section('content')
<div class="container py-4">

    <div class="mb-4">
        <h1 class="h3 mb-1">Nueva cuenta</h1>
        <p class="text-muted mb-0">Agrega una cuenta al catálogo contable.</p>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form action="{{ route('accounts.store') }}" method="POST">
                @csrf

                @include('accounting.accounts._form')

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary">
                        Cancelar
                    </a>

                    <button type="submit" class="btn btn-primary">
                        Guardar cuenta
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection