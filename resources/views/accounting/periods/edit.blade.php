@extends('layouts.app')

@section('content')
<div class="container py-4">

    <div class="mb-4">
        <h1 class="h3 mb-1">Editar período contable</h1>
        <p class="text-muted mb-0">Modifica los datos del período {{ $accountingPeriod->name }}.</p>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form action="{{ route('accounting-periods.update', $accountingPeriod) }}" method="POST">
                @csrf
                @method('PUT')

                @include('accounting.periods._form')

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('accounting-periods.index') }}" class="btn btn-outline-secondary">
                        Cancelar
                    </a>

                    <button type="submit" class="btn btn-primary">
                        Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
