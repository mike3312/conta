<form method="GET" class="card mb-4">
    <input type="hidden" name="review_status" value="{{ $report['filters']['review_status'] }}">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Fecha inicial</label>
                <input type="date" name="date_from" value="{{ $report['filters']['date_from'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Fecha final</label>
                <input type="date" name="date_to" value="{{ $report['filters']['date_to'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Período contable</label>
                <select name="accounting_period_id" class="form-select" @disabled($periods->isEmpty())>
                    @forelse($periods as $period)
                        <option value="{{ $period->id }}" @selected(($report['filters']['accounting_period_id'] ?? '') == $period->id)>
                            {{ $period->name }} · {{ $period->start_date->format('d/m/Y') }} al {{ $period->end_date->format('d/m/Y') }}
                        </option>
                    @empty
                        <option value="">No hay períodos disponibles</option>
                    @endforelse
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tipo</label>
                <select name="document_type" class="form-select">
                    <option value="">Todos</option>
                    @foreach($types as $type)
                        <option value="{{ $type->value }}" @selected(($report['filters']['document_type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Categoría fiscal</label>
                <select name="tax_category" class="form-select">
                    <option value="">Todas</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->value }}" @selected(($report['filters']['tax_category'] ?? '') === $category->value)>{{ $category->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">NIT {{ strtolower($thirdPartyLabel) }}</label>
                <input name="third_party_tax_id" value="{{ $report['filters']['third_party_tax_id'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ $thirdPartyLabel }}</label>
                <input name="third_party_name" value="{{ $report['filters']['third_party_name'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Serie</label>
                <input name="series" value="{{ $report['filters']['series'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Número</label>
                <input name="document_number" value="{{ $report['filters']['document_number'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">UUID</label>
                <input name="authorization_uuid" value="{{ $report['filters']['authorization_uuid'] ?? '' }}" class="form-control">
            </div>
            @if($direction->value === 'PURCHASE')
                <div class="col-md-2">
                    <label class="form-label">Crédito fiscal</label>
                    <select name="grants_tax_credit" class="form-select">
                        <option value="">Todos</option>
                        <option value="1" @selected(($report['filters']['grants_tax_credit'] ?? '') === '1')>Sí</option>
                        <option value="0" @selected(($report['filters']['grants_tax_credit'] ?? '') === '0')>No</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Pequeño contribuyente</label>
                    <select name="is_small_taxpayer" class="form-select">
                        <option value="">Todos</option>
                        <option value="1" @selected(($report['filters']['is_small_taxpayer'] ?? '') === '1')>Sí</option>
                        <option value="0" @selected(($report['filters']['is_small_taxpayer'] ?? '') === '0')>No</option>
                    </select>
                </div>
            @endif
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-primary">Aplicar filtros</button>
                <a href="{{ route($routePrefix.'.index') }}" class="btn btn-outline-secondary">Limpiar filtros</a>
            </div>
        </div>
    </div>
</form>
