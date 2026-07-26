@extends('layouts.app')

@section('title', 'Nueva empresa')
@section('breadcrumbs')<li class="breadcrumb-item"><a href="{{ route('companies.index') }}">Empresas</a></li><li class="breadcrumb-item active">Nueva</li>@endsection

@section('content')

<x-page-header title="Nueva empresa" subtitle="Registra una empresa para comenzar a trabajar en el sistema." icon="bi-building-add" />

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
