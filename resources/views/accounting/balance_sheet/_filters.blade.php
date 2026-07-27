<div class="card shadow-sm mb-4 balance-sheet-no-print">
    <div class="card-header bg-white">
        <strong>Filtros del reporte</strong>
    </div>

    <div class="card-body">
        <form method="GET" action="{{ route('accounting.balance-sheet.index') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Fecha de corte</label>
                <input type="date" name="cutoff_date" class="form-control" value="{{ $filters['cutoff_date'] }}">
            </div>

            <div class="col-md-3">
                <label class="form-label">Período contable</label>
                <select name="accounting_period_id" class="form-select">
                    <option value="">Seleccionar por fecha</option>
                    @foreach($periods as $period)
                        <option value="{{ $period->id }}" @selected((string) ($filters['accounting_period_id'] ?? '') === (string) $period->id)>
                            {{ $period->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Buscar cuenta</label>
                <input type="search" name="search" class="form-control" maxlength="255" value="{{ $filters['search'] ?? '' }}" placeholder="Código o nombre de cuenta">
            </div>

            <div class="col-md-2">
                <div class="form-check">
                    <input type="checkbox" name="show_zero_balances" value="1" class="form-check-input" id="show_zero_balances" @checked($filters['show_zero_balances'])>
                    <label class="form-check-label" for="show_zero_balances">
                        Mostrar cuentas sin saldo
                    </label>
                </div>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-bar-chart me-1"></i>Generar
                </button>
                <a href="{{ route('accounting.balance-sheet.index') }}" class="btn btn-outline-secondary">
                    Limpiar filtros
                </a>
                <a href="{{ route('accounting.balance-sheet.export.pdf', $filters) }}" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>Exportar PDF</a>
                <a href="{{ route('accounting.balance-sheet.export.excel', $filters) }}" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel</a>
            </div>
        </form>
    </div>
</div>
