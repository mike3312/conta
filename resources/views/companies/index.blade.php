@extends('layouts.app')

@section('title', 'Empresas')
@section('breadcrumbs')<li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Resumen</a></li><li class="breadcrumb-item active">Empresas</li>@endsection

@section('content')

<x-page-header title="Empresas" subtitle="Administra las organizaciones asociadas a tu usuario." icon="bi-buildings"><x-slot:actions><x-action-button :href="route('companies.create')" icon="bi-plus-lg">Nueva empresa</x-action-button></x-slot:actions></x-page-header>

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
                                    <x-status-badge :status="$company->status" />
                                </td>
                                <td class="text-end">
                                    <a href="#" class="btn btn-sm btn-outline-secondary">
                                        Entrar
                                    </a>

                                    <a href="{{ route('companies.edit', $company) }}" class="btn btn-sm btn-outline-primary">
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
