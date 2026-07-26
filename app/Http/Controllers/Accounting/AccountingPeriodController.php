<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\AccountingPeriodStatus;
use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Services\Accounting\CloseAccountingPeriodService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountingPeriodController extends Controller
{
    public function index()
    {
        $periods = AccountingPeriod::where('company_id', session('company_id'))
            ->with('closedBy:id,name')
            ->orderByDesc('start_date')
            ->get();

        return view('accounting.periods.index', compact('periods'));
    }

    public function create()
    {
        return view('accounting.periods.create');
    }

    public function store(Request $request)
    {
        $companyId = (int) session('company_id');

        if (! $companyId) {
            return redirect()->route('companies.index')->with('error', 'Primero debes seleccionar una empresa activa.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        if ($this->hasOverlappingPeriod($companyId, $validated['start_date'], $validated['end_date'])) {
            return back()->withInput()->withErrors([
                'start_date' => 'Las fechas se traslapan con otro período contable de esta empresa.',
            ]);
        }

        AccountingPeriod::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => AccountingPeriodStatus::OPEN->value,
            'closed_at' => null,
            'closed_by' => null,
        ]);

        return redirect()->route('accounting-periods.index')->with('success', 'Período contable creado correctamente.');
    }

    public function edit(AccountingPeriod $accountingPeriod)
    {
        $this->ensurePeriodBelongsToActiveCompany($accountingPeriod);
        $this->ensurePeriodIsOpen($accountingPeriod);

        return view('accounting.periods.edit', compact('accountingPeriod'));
    }

    public function update(Request $request, AccountingPeriod $accountingPeriod)
    {
        $companyId = (int) session('company_id');
        $this->ensurePeriodBelongsToActiveCompany($accountingPeriod);
        $this->ensurePeriodIsOpen($accountingPeriod);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        if ($this->hasOverlappingPeriod($companyId, $validated['start_date'], $validated['end_date'], $accountingPeriod->id)) {
            return back()->withInput()->withErrors([
                'start_date' => 'Las fechas se traslapan con otro período contable de esta empresa.',
            ]);
        }

        $accountingPeriod->update($validated);

        return redirect()->route('accounting-periods.index')->with('success', 'Período contable actualizado correctamente.');
    }

    public function destroy(AccountingPeriod $accountingPeriod)
    {
        abort_unless((int) $accountingPeriod->company_id === (int) session('company_id'), 404);

        if ($accountingPeriod->status === AccountingPeriodStatus::CLOSED) {
            return redirect()->route('accounting-periods.index')->with('error', 'No se puede eliminar un período contable cerrado.');
        }

        if ($accountingPeriod->journalEntries()->exists()) {
            return redirect()->route('accounting-periods.index')->with('error', 'No se puede eliminar el período porque contiene pólizas o movimientos contables.');
        }

        try {
            $accountingPeriod->delete();
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1451) {
                return redirect()->route('accounting-periods.index')->with('error', 'No se puede eliminar el período porque tiene información contable relacionada.');
            }

            throw $exception;
        }

        return redirect()->route('accounting-periods.index')->with('success', 'Período contable eliminado correctamente.');
    }

    public function close(AccountingPeriod $accountingPeriod, Request $request, CloseAccountingPeriodService $closePeriod): RedirectResponse
    {
        $closePeriod->close($accountingPeriod, (int) session('company_id'), $request->user()->id);

        return redirect()->route('accounting-periods.index')->with('success', 'El período contable fue cerrado correctamente.');
    }

    private function hasOverlappingPeriod(int $companyId, string $startDate, string $endDate, ?int $exceptId = null): bool
    {
        return AccountingPeriod::where('company_id', $companyId)
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();
    }

    private function ensurePeriodBelongsToActiveCompany(AccountingPeriod $accountingPeriod): void
    {
        abort_unless((int) $accountingPeriod->company_id === (int) session('company_id'), 403);
    }

    private function ensurePeriodIsOpen(AccountingPeriod $accountingPeriod): void
    {
        abort_unless($accountingPeriod->status === AccountingPeriodStatus::OPEN, 403, 'No se puede modificar un período contable cerrado.');
    }
}
