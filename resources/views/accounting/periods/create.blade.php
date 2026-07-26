@extends('layouts.app')

@section('content')
<div class="container py-4">

    <x-page-header title="Nuevo período contable" subtitle="Agrega un período contable a la empresa activa." icon="bi-calendar-plus" />

    <div class="card shadow-sm">
        <div class="card-body">
            <form action="{{ route('accounting-periods.store') }}" method="POST">
                @csrf

                @include('accounting.periods._form')

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('accounting-periods.index') }}" class="btn btn-outline-secondary">
                        Cancelar
                    </a>

                    <button type="submit" class="btn btn-primary">
                        Guardar período
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
