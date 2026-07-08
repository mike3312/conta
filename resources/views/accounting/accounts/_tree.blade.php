<ul class="list-group list-group-flush">
    @foreach($accounts as $account)
        <li class="list-group-item">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>{{ $account->code }}</strong>
                    <span>{{ $account->name }}</span>

                    <span class="badge bg-light text-dark border ms-2">
                        {{ $account->account_type->label() }}
                    </span>

                    <span class="badge bg-secondary ms-1">
                        {{ $account->nature->label() }}
                    </span>

                    @if($account->allows_entries)
<span class="badge bg-primary ms-1">Movimiento</span>
                    @else
                        <span class="badge bg-info ms-1">Encabezado</span>
                    @endif
                </div>
@if($account->is_active)
    <span class="badge bg-success ms-1">Activa</span>
@else
    <span class="badge bg-danger ms-1">Inactiva</span>
@endif
                <div class="d-flex gap-1">
    <a href="{{ route('accounts.edit', $account) }}" class="btn btn-sm btn-outline-secondary">
        Editar
    </a>

    <form action="{{ route('accounts.destroy', $account) }}" method="POST"
          onsubmit="return confirm('¿Seguro que deseas eliminar esta cuenta?');">
        @csrf
        @method('DELETE')

        <button type="submit" class="btn btn-sm btn-outline-danger">
            Eliminar
        </button>
    </form>
</div>
            </div>

            @if($account->children->count())
                <div class="ms-4 mt-2">
                    @include('accounting.accounts._tree', ['accounts' => $account->children])
                </div>
            @endif
        </li>
    @endforeach
</ul>