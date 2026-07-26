@extends('layouts.app')

@section('content')
<div class="container py-4">
    <x-page-header title="Nueva póliza contable" subtitle="Guarda una partida como borrador para completarla o contabilizarla después." icon="bi-journal-plus" />

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>No se pudo guardar la póliza.</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('journal-entries.store') }}" method="POST">
        @csrf
        @include('accounting.journal_entries._form')

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('journal-entries.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar borrador</button>
        </div>
    </form>
</div>
@endsection
