<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\AccountingPeriodStatus;
use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use Illuminate\Http\Request;

class AccountingPeriodController extends Controller
{
    public function index()
    {
        $companyId = session('company_id');

        $periods = AccountingPeriod::where('company_id', $companyId)
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
        $companyId = session('company_id');

        if (! $companyId) {
            return redirect()
                ->route('companies.index')
                ->with('error', 'Primero debes seleccionar una empresa activa.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        if ($this->hasOverlappingPeriod($companyId, $validated['start_date'], $validated['end_date'])) {
            return back()
                ->withInput()
                ->withErrors([
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

        return redirect()
            ->route('accounting-periods.index')
            ->with('success', 'Período contable creado correctamente.');
    }

    public function edit(AccountingPeriod $accountingPeriod)
    {
        $this->ensurePeriodBelongsToActiveCompany($accountingPeriod);

        return view('accounting.periods.edit', compact('accountingPeriod'));
    }

    public function update(Request $request, AccountingPeriod $accountingPeriod)
    {
        $companyId = session('company_id');

        $this->ensurePeriodBelongsToActiveCompany($accountingPeriod);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        if ($this->hasOverlappingPeriod(
            $companyId,
            $validated['start_date'],
            $validated['end_date'],
            $accountingPeriod->id
        )) {
            return back()
                ->withInput()
                ->withErrors([
                    'start_date' => 'Las fechas se traslapan con otro período contable de esta empresa.',
                ]);
        }

        $accountingPeriod->update([
            'name' => $validated['name'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        return redirect()
            ->route('accounting-periods.index')
            ->with('success', 'Período contable actualizado correctamente.');
    }

    public function destroy(AccountingPeriod $accountingPeriod)
    {
        $this->ensurePeriodBelongsToActiveCompany($accountingPeriod);

        $accountingPeriod->delete();

        return redirect()
            ->route('accounting-periods.index')
            ->with('success', 'Período contable eliminado correctamente.');
    }

    private function hasOverlappingPeriod(
        int $companyId,
        string $startDate,
        string $endDate,
        ?int $exceptId = null
    ): bool {
        return AccountingPeriod::where('company_id', $companyId)
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();
    }

    private function ensurePeriodBelongsToActiveCompany(AccountingPeriod $accountingPeriod): void
    {
        if ((int) $accountingPeriod->company_id !== (int) session('company_id')) {
            abort(403);
        }
    }
}
