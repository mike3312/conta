@extends('layouts.app')

@section('title', 'Nueva empresa')

@section('content')

<div class="mb-4">
    <h1 class="h3 mb-1">Nueva empresa</h1>
    <p class="text-muted mb-0">
        Registra una empresa para comenzar a trabajar en el sistema.
    </p>
</div>

<div class="card shadow-sm">
    <div class="card-body">

        <form method="POST" action="{{ route('companies.store') }}">
            @csrf

            @include('companies.partials.form')

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('companies.index') }}" class="btn btn-outline-secondary">
                    Cancelar
                </a>

                <button type="submit" class="btn btn-primary">
                    Guardar empresa
                </button>
            </div>
        </form>

    </div>
</div>

@endsection