@extends('layouts.app')
@section('title', 'Editar '.$documentLabel)
@section('content')
<x-page-header title="Editar {{ $documentLabel }} fiscal" :subtitle="$bookTitle" icon="bi-pencil" />
<form method="POST" action="{{ route($routePrefix.'.update',$document->id) }}" class="card"><div class="card-body p-4">@csrf @method('PUT') @include('fiscal_documents._form')</div><div class="card-footer d-flex justify-content-end gap-2"><a href="{{ route($routePrefix.'.show',$document->id) }}" class="btn btn-outline-secondary">Cancelar</a><button class="btn btn-primary">Guardar cambios</button></div></form>
@endsection
