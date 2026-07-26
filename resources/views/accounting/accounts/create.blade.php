@extends('layouts.app')

@section('content')
<div class="container py-4">

    <x-page-header title="Nueva cuenta" subtitle="Agrega una cuenta al catálogo contable." icon="bi-node-plus" />

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
