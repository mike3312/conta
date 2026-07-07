@extends('layouts.app')

@section('title', 'Empresas')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Empresas</h1>

    <a href="{{ route('companies.create') }}" class="btn btn-primary">
        Nueva empresa
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="card shadow-sm">
    <div class="card-body">

        @if($companies->isEmpty())

            <p class="text-muted mb-0">
                Aún no tienes empresas registradas.
            </p>

        @else

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Razón social</th>
                            <th>NIT</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($companies as $company)
                            <tr>
                                <td>{{ $company->name }}</td>
                                <td>{{ $company->legal_name }}</td>
                                <td>{{ $company->tax_id }}</td>
                                <td>
                                    <span class="badge bg-success">
                                        {{ $company->status->value ?? $company->status }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="#" class="btn btn-sm btn-outline-secondary">
                                        Entrar
                                    </a>

                                    <a href="#" class="btn btn-sm btn-outline-primary">
                                        Editar
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        @endif

    </div>
</div>

@endsection