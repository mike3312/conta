<div class="row g-3">

    <div class="col-md-12">
        <label class="form-label">Nombre</label>
        <input type="text"
               name="name"
               class="form-control"
               value="{{ old('name', $accountingPeriod->name ?? '') }}"
               placeholder="Ej. Enero 2026">

        @error('name')
            <div class="text-danger small">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Fecha inicial</label>
        <input type="date"
               name="start_date"
               class="form-control"
               value="{{ old('start_date', isset($accountingPeriod) ? $accountingPeriod->start_date->format('Y-m-d') : '') }}">

        @error('start_date')
            <div class="text-danger small">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Fecha final</label>
        <input type="date"
               name="end_date"
               class="form-control"
               value="{{ old('end_date', isset($accountingPeriod) ? $accountingPeriod->end_date->format('Y-m-d') : '') }}">

        @error('end_date')
            <div class="text-danger small">{{ $message }}</div>
        @enderror
    </div>

</div>
