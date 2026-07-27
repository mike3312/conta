@extends('layouts.app')

@section('title', 'Editar empresa')
@section('breadcrumbs')<li class="breadcrumb-item"><a href="{{ route('companies.index') }}">Empresas</a></li><li class="breadcrumb-item active">Editar</li>@endsection

@section('content')

<x-page-header title="Editar empresa" subtitle="Actualiza la información de {{ $company->name }}." icon="bi-building-gear" />

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('companies.update', $company) }}">
            @csrf
            @method('PUT')

            @include('companies.partials.form', ['editing' => true])

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('companies.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Actualizar empresa</button>
            </div>
        </form>
    </div>
</div>

@endsection
