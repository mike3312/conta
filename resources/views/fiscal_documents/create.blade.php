@extends('layouts.app')
@section('title', 'Registrar '.$documentLabel)
@section('content')
<x-page-header title="Registrar {{ $documentLabel }} fiscal" :subtitle="$bookTitle" icon="bi-plus-circle" />
<form method="POST" action="{{ route($routePrefix.'.store') }}" class="card"><div class="card-body p-4">@csrf @include('fiscal_documents._form')</div><div class="card-footer d-flex justify-content-end gap-2"><a href="{{ route($routePrefix.'.index') }}" class="btn btn-outline-secondary">Cancelar</a><button class="btn btn-primary">Guardar</button></div></form>
@endsection
