@extends('layouts.app')

@section('content')
<div class="container py-4">

    <x-page-header title="Editar período contable" :subtitle="'Modifica los datos del período '.$accountingPeriod->name.'.'" icon="bi-calendar-check" />

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
