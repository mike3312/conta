@extends('layouts.app')

@section('content')
<div class="container py-4">

    <x-page-header title="Editar cuenta" :subtitle="'Modifica los datos de '.$account->code.' - '.$account->name.'.'" icon="bi-pencil-square" />

    <div class="card shadow-sm">
        <div class="card-body">
            <form action="{{ route('accounts.update', $account) }}" method="POST">
                @csrf
                @method('PUT')

                @include('accounting.accounts._form')

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary">
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
