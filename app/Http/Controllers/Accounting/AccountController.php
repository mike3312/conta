<?php

namespace App\Http\Controllers\Accounting;
use Illuminate\Validation\Rule;
use App\Enums\AccountNature;
use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index()
    {
        $companyId = session('company_id');

        $accounts = Account::where('company_id', $companyId)
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('code')
            ->get();

        return view('accounting.accounts.index', compact('accounts'));
    }

    public function create()
    {
        $companyId = session('company_id');

        $parentAccounts = Account::where('company_id', $companyId)
    ->where('allows_entries', false)
    ->where('is_active', true)
    ->orderBy('code')
    ->get();

        $types = AccountType::cases();
        $natures = AccountNature::cases();

        return view('accounting.accounts.create', compact(
            'parentAccounts',
            'types',
            'natures'
        ));
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
        'parent_id' => ['nullable', 'exists:accounts,id'],
        'code' => [
    'required',
    'string',
    'max:50',
    Rule::unique('accounts', 'code')->where(function ($query) use ($companyId) {
        return $query->where('company_id', $companyId);
    }),
],
        'name' => ['required', 'string', 'max:255'],
        'account_type' => ['required', 'string'],
        'allows_entries' => ['nullable', 'boolean'],
        'is_active' => ['nullable', 'boolean'],
    ]);

    $parent = null;

    if (!empty($validated['parent_id'])) {
        $parent = Account::where('company_id', $companyId)
    ->where('is_active', true)
    ->findOrFail($validated['parent_id']);

        if ($parent->allows_entries) {
            return back()
                ->withInput()
                ->withErrors([
                    'parent_id' => 'Una cuenta de movimiento no puede tener cuentas hijas.',
                ]);
        }
    }

    $accountType = AccountType::from($validated['account_type']);
    $nature = $accountType->defaultNature();

    Account::create([
        'company_id' => $companyId,
        'parent_id' => $validated['parent_id'] ?? null,
        'code' => $validated['code'],
        'name' => $validated['name'],
        'account_type' => $accountType->value,
        'nature' => $nature->value,
        'allows_entries' => $request->boolean('allows_entries'),
        'level' => $parent ? $parent->level + 1 : 1,
        'is_active' => $request->boolean('is_active', true),
    ]);

    return redirect()
        ->route('accounts.index')
        ->with('success', 'Cuenta creada correctamente.');
}

public function edit(Account $account)
{
    $companyId = session('company_id');

    if ((int) $account->company_id !== (int) $companyId) {
        abort(403);
    }
$parentAccounts = Account::where('company_id', $companyId)
    ->where('allows_entries', false)
    ->where('is_active', true)
    ->where('id', '!=', $account->id)
    ->orderBy('code')
    ->get();

    $types = AccountType::cases();

    return view('accounting.accounts.edit', compact(
        'account',
        'parentAccounts',
        'types'
    ));
}

public function update(Request $request, Account $account)
{
    $companyId = session('company_id');

    if ((int) $account->company_id !== (int) $companyId) {
        abort(403);
    }

    $validated = $request->validate([
        'parent_id' => ['nullable', 'exists:accounts,id'],
'code' => [
    'required',
    'string',
    'max:50',
    Rule::unique('accounts', 'code')
        ->where(fn ($query) => $query->where('company_id', $companyId))
        ->ignore($account->id),
],
        'name' => ['required', 'string', 'max:255'],
        'account_type' => ['required', 'string'],
        'allows_entries' => ['nullable', 'boolean'],
        'is_active' => ['nullable', 'boolean'],
    ]);

    $parent = null;

    if (!empty($validated['parent_id'])) {
        $parent = Account::where('company_id', $companyId)
    ->where('is_active', true)
    ->findOrFail($validated['parent_id']);

        if ($parent->allows_entries) {
            return back()
                ->withInput()
                ->withErrors([
                    'parent_id' => 'Una cuenta de movimiento no puede tener cuentas hijas.',
                ]);
        }
    }

    if ($request->boolean('allows_entries') && $account->children()->exists()) {
        return back()
            ->withInput()
            ->withErrors([
                'allows_entries' => 'Una cuenta con cuentas hijas no puede permitir movimientos.',
            ]);
    }

    $accountType = AccountType::from($validated['account_type']);
    $nature = $accountType->defaultNature();

    $account->update([
        'parent_id' => $validated['parent_id'] ?? null,
        'code' => $validated['code'],
        'name' => $validated['name'],
        'account_type' => $accountType->value,
        'nature' => $nature->value,
        'allows_entries' => $request->boolean('allows_entries'),
        'level' => $parent ? $parent->level + 1 : 1,
        'is_active' => $request->boolean('is_active'),
    ]);

    return redirect()
        ->route('accounts.index')
        ->with('success', 'Cuenta actualizada correctamente.');
}
public function destroy(Account $account)
{
    $companyId = session('company_id');

    if ((int) $account->company_id !== (int) $companyId) {
        abort(403);
    }

    if ($account->hasChildren()) {
        return redirect()
            ->route('accounts.index')
            ->with('error', 'No puedes eliminar una cuenta que tiene cuentas hijas.');
    }

    $account->delete();

    return redirect()
        ->route('accounts.index')
        ->with('success', 'Cuenta eliminada correctamente.');
}

}