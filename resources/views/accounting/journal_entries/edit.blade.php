@extends('layouts.app')

@section('content')
<div class="container py-4">
    <x-page-header title="Editar póliza en borrador" subtitle="Modifica el encabezado y las líneas antes de contabilizar." icon="bi-pencil-square" />

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>No se pudo actualizar la póliza.</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('journal-entries.update', $journalEntry) }}" method="POST">
        @csrf
        @method('PUT')
        @include('accounting.journal_entries._form')

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('journal-entries.show', $journalEntry) }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
    </form>
</div>
@endsection
