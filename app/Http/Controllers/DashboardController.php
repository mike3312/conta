<?php

namespace App\Http\Controllers;

use App\Services\Accounting\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard): View
    {
        $companyId = (int) session('company_id');
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'accounting_period_id' => [
                'nullable', 'integer',
                Rule::exists('accounting_periods', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
        ]);

        if (! $companyId) {
            return view('dashboard.index', ['dashboard' => null]);
        }

        $company = $request->user()->companies()
            ->active()
            ->wherePivot('is_active', true)
            ->where('companies.id', $companyId)
            ->firstOrFail();

        return view('dashboard.index', ['dashboard' => $dashboard->get($company, $filters)]);
    }
}
