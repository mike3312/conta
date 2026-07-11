<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <strong>Filtros del reporte</strong>
    </div>

    <div class="card-body">
        <form method="GET" action="{{ route('accounting.trial-balance.index') }}" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Fecha inicial</label>
                <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
            </div>

            <div class="col-md-2">
                <label class="form-label">Fecha final</label>
                <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
            </div>

            <div class="col-md-3">
                <label class="form-label">Período contable</label>
                <select name="accounting_period_id" class="form-select">
                    <option value="">Seleccionar por fechas</option>
                    @foreach($periods as $period)
                        <option value="{{ $period->id }}" @selected((string) ($filters['accounting_period_id'] ?? '') === (string) $period->id)>
                            {{ $period->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Tipo de cuenta</label>
                <select name="account_type" class="form-select">
                    <option value="">Todos los tipos</option>
                    @foreach($accountTypes as $type)
                        <option value="{{ $type->value }}" @selected(($filters['account_type'] ?? '') === $type->value)>
                            {{ $type->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Buscar cuenta</label>
                <input type="search" name="search" class="form-control" maxlength="255" value="{{ $filters['search'] ?? '' }}" placeholder="Código o nombre">
            </div>

            <div class="col-md-4">
                <label class="form-label">Cuenta inicial</label>
                <select name="account_from_id" class="form-select">
                    <option value="">Desde la primera</option>
                    @foreach($accountOptions as $account)
                        <option value="{{ $account->id }}" @selected((string) ($filters['account_from_id'] ?? '') === (string) $account->id)>
                            {{ $account->code }} - {{ $account->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Cuenta final</label>
                <select name="account_to_id" class="form-select">
                    <option value="">Hasta la última</option>
                    @foreach($accountOptions as $account)
                        <option value="{{ $account->id }}" @selected((string) ($filters['account_to_id'] ?? '') === (string) $account->id)>
                            {{ $account->code }} - {{ $account->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <div class="form-check mb-2">
                    <input type="checkbox" name="show_without_movements" value="1" class="form-check-input" id="show_without_movements" @checked($filters['show_without_movements'] ?? false)>
                    <label class="form-check-label" for="show_without_movements">
                        Mostrar cuentas sin movimientos
                    </label>
                </div>

                <div class="form-check">
                    <input type="checkbox" name="only_with_balance" value="1" class="form-check-input" id="only_with_balance" @checked($filters['only_with_balance'] ?? false)>
                    <label class="form-check-label" for="only_with_balance">
                        Mostrar únicamente cuentas con saldo
                    </label>
                </div>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel me-1"></i>Filtrar
                </button>
                <a href="{{ route('accounting.trial-balance.index') }}" class="btn btn-outline-secondary">
                    Limpiar filtros
                </a>
            </div>
        </form>
    </div>
</div>
