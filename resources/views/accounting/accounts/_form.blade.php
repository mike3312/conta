<div class="row g-3">

    <div class="col-md-6">
        <label class="form-label">Cuenta padre</label>
        <select name="parent_id" class="form-select">
            <option value="">Sin cuenta padre</option>

            @foreach($parentAccounts as $parent)
                <option value="{{ $parent->id }}" @selected(old('parent_id', $account->parent_id ?? '') == $parent->id)>
                    {{ $parent->indented_name }}
                </option>
            @endforeach
        </select>

        @error('parent_id')
            <div class="text-danger small">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Código</label>
        <input type="text"
               name="code"
               class="form-control"
               value="{{ old('code', $account->code ?? '') }}"
               placeholder="Ej. 1.1.01">

        @error('code')
            <div class="text-danger small">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Nombre</label>
        <input type="text"
               name="name"
               class="form-control"
               value="{{ old('name', $account->name ?? '') }}"
               placeholder="Ej. Caja general">

        @error('name')
            <div class="text-danger small">{{ $message }}</div>
        @enderror
    </div>

<div class="col-md-6">
    <label class="form-label">Tipo</label>
    <select name="account_type" class="form-select">
        @foreach($types as $type)
            <option value="{{ $type->value }}" @selected(old('account_type', $account->account_type->value ?? '') === $type->value)>
                {{ $type->label() }}
            </option>
        @endforeach
    </select>

    @error('account_type')
        <div class="text-danger small">{{ $message }}</div>
    @enderror
</div>


    <div class="col-md-6">
        <div class="form-check mt-4">
            <input type="checkbox"
                   name="allows_entries"
                   value="1"
                   class="form-check-input"
                   id="allows_entries"
                   @checked(old('allows_entries', $account->allows_entries ?? false))>

            <label class="form-check-label" for="allows_entries">
                Permite movimientos contables
            </label>
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-check mt-4">
            <input type="checkbox"
                   name="is_active"
                   value="1"
                   class="form-check-input"
                   id="is_active"
                   @checked(old('is_active', $account->is_active ?? true))>

            <label class="form-check-label" for="is_active">
                Cuenta activa
            </label>
        </div>
    </div>

</div>