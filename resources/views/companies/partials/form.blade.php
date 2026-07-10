<div class="row g-3">

    <div class="col-md-6">
        <label class="form-label">Nombre comercial</label>
        <input
            type="text"
            name="name"
            value="{{ old('name', $company->name ?? '') }}"
            class="form-control @error('name') is-invalid @enderror"
        >
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Razón social</label>
        <input
            type="text"
            name="legal_name"
            value="{{ old('legal_name', $company->legal_name ?? '') }}"
            class="form-control @error('legal_name') is-invalid @enderror"
        >
        @error('legal_name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">NIT</label>
        <input
            type="text"
            name="tax_id"
            value="{{ old('tax_id', $company->tax_id ?? '') }}"
            class="form-control @error('tax_id') is-invalid @enderror"
        >
        @error('tax_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Correo</label>
        <input
            type="email"
            name="email"
            value="{{ old('email', $company->email ?? '') }}"
            class="form-control @error('email') is-invalid @enderror"
        >
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Teléfono</label>
        <input
            type="text"
            name="phone"
            value="{{ old('phone', $company->phone ?? '') }}"
            class="form-control @error('phone') is-invalid @enderror"
        >
        @error('phone')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-12">
        <label class="form-label">Dirección</label>
        <textarea
            name="address"
            rows="2"
            class="form-control @error('address') is-invalid @enderror"
        >{{ old('address', $company->address ?? '') }}</textarea>
        @error('address')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Municipio / Ciudad</label>
        <input
            type="text"
            name="city"
            value="{{ old('city', $company->city ?? '') }}"
            class="form-control @error('city') is-invalid @enderror"
        >
        @error('city')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Departamento</label>
        <input
            type="text"
            name="state"
            value="{{ old('state', $company->state ?? '') }}"
            class="form-control @error('state') is-invalid @enderror"
        >
        @error('state')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
    <hr>

    <div class="card border-primary">
        <div class="card-body">

            <div class="form-check">
                <input
                    class="form-check-input"
                    type="checkbox"
                    id="create_default_catalog"
                    name="create_default_catalog"
                    value="1"
                    checked
                >

                <label class="form-check-label fw-semibold" for="create_default_catalog">
                    Crear catálogo contable base
                </label>

                <div class="form-text">
                    Se generará automáticamente un catálogo inicial con cuentas de Activos,
                    Pasivos, Capital, Ingresos, Gastos y Costos. Luego podrás modificarlo según
                    las necesidades de la empresa.
                </div>
            </div>

        </div>
    </div>
</div>
</div>