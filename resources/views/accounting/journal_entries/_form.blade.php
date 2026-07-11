@php
    $formLines = old('lines');

    if ($formLines === null && isset($journalEntry)) {
        $formLines = $journalEntry->lines->map(fn ($line) => [
            'account_id' => $line->account_id,
            'description' => $line->description,
            'debit' => $line->debit,
            'credit' => $line->credit,
        ])->toArray();
    }

    if ($formLines === null) {
        $formLines = [
            ['account_id' => '', 'description' => '', 'debit' => '0.00', 'credit' => '0.00'],
            ['account_id' => '', 'description' => '', 'debit' => '0.00', 'credit' => '0.00'],
        ];
    }
@endphp

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <strong>Encabezado</strong>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Período contable</label>
                <select name="accounting_period_id" class="form-select" required>
                    <option value="">Selecciona un período</option>
                    @foreach($periods as $period)
                        <option value="{{ $period->id }}" @selected((string) old('accounting_period_id', $journalEntry->accounting_period_id ?? '') === (string) $period->id)>
                            {{ $period->name }} ({{ $period->start_date->format('d/m/Y') }} - {{ $period->end_date->format('d/m/Y') }})
                        </option>
                    @endforeach
                </select>
                @error('accounting_period_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">Fecha</label>
                <input type="date" name="entry_date" class="form-control" required value="{{ old('entry_date', isset($journalEntry) ? $journalEntry->entry_date->format('Y-m-d') : '') }}">
                @error('entry_date')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-5">
                <label class="form-label">Referencia</label>
                <input type="text" name="reference" class="form-control" maxlength="255" value="{{ old('reference', $journalEntry->reference ?? '') }}" placeholder="Documento o referencia opcional">
                @error('reference')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label class="form-label">Descripción</label>
                <input type="text" name="description" class="form-control" maxlength="255" required value="{{ old('description', $journalEntry->description ?? '') }}" placeholder="Concepto general de la póliza">
                @error('description')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong>Líneas contables</strong>
        <button type="button" class="btn btn-sm btn-outline-primary" id="add-journal-line">
            <i class="bi bi-plus-lg me-1"></i>Agregar línea
        </button>
    </div>
    <div class="card-body">
        @error('lines')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th style="min-width: 260px;">Cuenta</th>
                        <th style="min-width: 220px;">Descripción</th>
                        <th style="min-width: 140px;" class="text-end">Debe</th>
                        <th style="min-width: 140px;" class="text-end">Haber</th>
                        <th style="width: 70px;"></th>
                    </tr>
                </thead>
                <tbody id="journal-lines">
                    @foreach($formLines as $index => $line)
                        <tr data-line-row>
                            <td>
                                <select name="lines[{{ $index }}][account_id]" class="form-select" required>
                                    <option value="">Selecciona una cuenta</option>
                                    @foreach($accounts as $account)
                                        <option value="{{ $account->id }}" @selected((string) ($line['account_id'] ?? '') === (string) $account->id)>
                                            {{ $account->code }} - {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error("lines.$index.account_id")<div class="text-danger small">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <input type="text" name="lines[{{ $index }}][description]" class="form-control" maxlength="255" value="{{ $line['description'] ?? '' }}">
                            </td>
                            <td>
                                <input type="number" name="lines[{{ $index }}][debit]" class="form-control text-end journal-debit" min="0" step="0.01" required value="{{ $line['debit'] ?? '0.00' }}">
                                @error("lines.$index.debit")<div class="text-danger small">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <input type="number" name="lines[{{ $index }}][credit]" class="form-control text-end journal-credit" min="0" step="0.01" required value="{{ $line['credit'] ?? '0.00' }}">
                                @error("lines.$index.credit")<div class="text-danger small">{{ $message }}</div>@enderror
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-journal-line" title="Eliminar línea">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="2" class="text-end">Totales</th>
                        <th class="text-end" id="total-debit">0.00</th>
                        <th class="text-end" id="total-credit">0.00</th>
                        <th></th>
                    </tr>
                    <tr>
                        <th colspan="3" class="text-end">Diferencia</th>
                        <th class="text-end" id="total-difference">0.00</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<template id="journal-line-template">
    <tr data-line-row>
        <td>
            <select name="lines[__INDEX__][account_id]" class="form-select" required>
                <option value="">Selecciona una cuenta</option>
                @foreach($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                @endforeach
            </select>
        </td>
        <td><input type="text" name="lines[__INDEX__][description]" class="form-control" maxlength="255"></td>
        <td><input type="number" name="lines[__INDEX__][debit]" class="form-control text-end journal-debit" min="0" step="0.01" required value="0.00"></td>
        <td><input type="number" name="lines[__INDEX__][credit]" class="form-control text-end journal-credit" min="0" step="0.01" required value="0.00"></td>
        <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-danger remove-journal-line" title="Eliminar línea">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    </tr>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const linesContainer = document.getElementById('journal-lines');
    const template = document.getElementById('journal-line-template');
    const addButton = document.getElementById('add-journal-line');
    let nextIndex = linesContainer.querySelectorAll('[data-line-row]').length;

    function amountValue(input) {
        const value = Number.parseFloat(input.value);
        return Number.isFinite(value) ? value : 0;
    }

    function updateTotals() {
        const debit = Array.from(linesContainer.querySelectorAll('.journal-debit'))
            .reduce((total, input) => total + amountValue(input), 0);
        const credit = Array.from(linesContainer.querySelectorAll('.journal-credit'))
            .reduce((total, input) => total + amountValue(input), 0);

        document.getElementById('total-debit').textContent = debit.toFixed(2);
        document.getElementById('total-credit').textContent = credit.toFixed(2);
        document.getElementById('total-difference').textContent = (debit - credit).toFixed(2);
    }

    addButton.addEventListener('click', function () {
        const html = template.innerHTML.replaceAll('__INDEX__', nextIndex++);
        linesContainer.insertAdjacentHTML('beforeend', html);
        updateTotals();
    });

    linesContainer.addEventListener('click', function (event) {
        const removeButton = event.target.closest('.remove-journal-line');
        if (removeButton) {
            removeButton.closest('[data-line-row]').remove();
            updateTotals();
        }
    });

    linesContainer.addEventListener('input', updateTotals);
    updateTotals();
});
</script>
