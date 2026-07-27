<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <strong>Filtros del reporte</strong>
    </div>

    <div class="card-body">
        <form method="GET" action="{{ route('accounting.daily-book.index') }}" class="row g-3 align-items-end">
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
                    <option value="">Todos los períodos</option>
                    @foreach($periods as $period)
                        <option value="{{ $period->id }}" @selected((string) ($filters['accounting_period_id'] ?? '') === (string) $period->id)>
                            {{ $period->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Número de póliza</label>
                <input type="number" name="number" class="form-control" min="1" value="{{ $filters['number'] ?? '' }}">
            </div>

            <div class="col-md-3">
                <label class="form-label">Cuenta contable</label>
                <select name="account_id" class="form-select">
                    <option value="">Todas las cuentas</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) ($filters['account_id'] ?? '') === (string) $account->id)>
                            {{ $account->code }} - {{ $account->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-8">
                <label class="form-label">Concepto o descripción</label>
                <input type="search" name="search" class="form-control" maxlength="255" value="{{ $filters['search'] ?? '' }}" placeholder="Buscar en la póliza o sus líneas">
            </div>

            <div class="col-md-4 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel me-1"></i>Aplicar filtros
                </button>

                <a href="{{ route('accounting.daily-book.index') }}" class="btn btn-outline-secondary">
                    Limpiar filtros
                </a>

                <a href="{{ route('accounting.daily-book.export.pdf', $filters) }}" class="btn btn-outline-danger">
                    <i class="bi bi-file-earmark-pdf me-1"></i>Exportar PDF
                </a>

                <a href="{{ route('accounting.daily-book.export.excel', $filters) }}" class="btn btn-outline-success">
                    <i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel
                </a>
            </div>
        </form>
    </div>
</div>
