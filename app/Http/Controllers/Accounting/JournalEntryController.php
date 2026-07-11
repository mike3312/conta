<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\AccountingPeriodStatus;
use App\Enums\JournalEntryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PostJournalEntryRequest;
use App\Http\Requests\StoreJournalEntryRequest;
use App\Http\Requests\UpdateJournalEntryRequest;
use App\Http\Requests\VoidJournalEntryRequest;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JournalEntryController extends Controller
{
    public function __construct(private JournalEntryService $journalEntryService) {}

    public function index(Request $request)
    {
        $companyId = session('company_id');

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::enum(JournalEntryStatus::class)],
            'search' => ['nullable', 'string', 'max:255'],
        ], [
            'date_from.date' => 'La fecha inicial no es válida.',
            'date_to.date' => 'La fecha final no es válida.',
            'date_to.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            'status' => 'El estado seleccionado no es válido.',
            'search.max' => 'La búsqueda no puede exceder 255 caracteres.',
        ]);

        $journalEntries = JournalEntry::where('company_id', $companyId)
            ->with(['accountingPeriod', 'creator'])
            ->withSum('lines as total_debit', 'debit')
            ->withSum('lines as total_credit', 'credit')
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('entry_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('entry_date', '<=', $date))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%");

                    if (ctype_digit($search)) {
                        $query->orWhere('number', (int) $search);
                    }
                });
            })
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $statuses = JournalEntryStatus::cases();

        return view('accounting.journal_entries.index', compact('journalEntries', 'statuses'));
    }

    public function create()
    {
        $companyId = session('company_id');

        if (! $companyId) {
            return redirect()
                ->route('companies.index')
                ->with('error', 'Primero debes seleccionar una empresa activa.');
        }

        [$periods, $accounts] = $this->getFormOptions($companyId);

        return view('accounting.journal_entries.create', compact('periods', 'accounts'));
    }

    public function store(StoreJournalEntryRequest $request)
    {
        $companyId = (int) session('company_id');

        if (! $companyId) {
            return redirect()
                ->route('companies.index')
                ->with('error', 'Primero debes seleccionar una empresa activa.');
        }

        $journalEntry = $this->journalEntryService->createDraft(
            $request->validated(),
            $companyId,
            $request->user()->id
        );

        return redirect()
            ->route('journal-entries.show', $journalEntry)
            ->with('success', 'Póliza guardada como borrador correctamente.');
    }

    public function show(JournalEntry $journalEntry)
    {
        $this->ensureEntryBelongsToActiveCompany($journalEntry);

        $journalEntry->load([
            'accountingPeriod',
            'lines.account',
            'creator',
            'updater',
            'postedBy',
            'voidedBy',
        ]);

        return view('accounting.journal_entries.show', compact('journalEntry'));
    }

    public function edit(JournalEntry $journalEntry)
    {
        $this->ensureEntryBelongsToActiveCompany($journalEntry);

        if ($journalEntry->status !== JournalEntryStatus::DRAFT) {
            return redirect()
                ->route('journal-entries.show', $journalEntry)
                ->with('error', 'Solo las pólizas en borrador pueden editarse.');
        }

        $journalEntry->loadMissing('accountingPeriod');

        if ($journalEntry->accountingPeriod->status !== AccountingPeriodStatus::OPEN) {
            return redirect()
                ->route('journal-entries.show', $journalEntry)
                ->with('error', 'No se puede editar una póliza de un período cerrado.');
        }

        $companyId = (int) session('company_id');
        [$periods, $accounts] = $this->getFormOptions($companyId);
        $journalEntry->load('lines');

        return view('accounting.journal_entries.edit', compact('journalEntry', 'periods', 'accounts'));
    }

    public function update(UpdateJournalEntryRequest $request, JournalEntry $journalEntry)
    {
        $this->ensureEntryBelongsToActiveCompany($journalEntry);

        $journalEntry = $this->journalEntryService->updateDraft(
            $journalEntry,
            $request->validated(),
            (int) session('company_id'),
            $request->user()->id
        );

        return redirect()
            ->route('journal-entries.show', $journalEntry)
            ->with('success', 'Borrador actualizado correctamente.');
    }

    public function destroy(JournalEntry $journalEntry)
    {
        $this->ensureEntryBelongsToActiveCompany($journalEntry);

        $this->journalEntryService->deleteDraft($journalEntry, (int) session('company_id'));

        return redirect()
            ->route('journal-entries.index')
            ->with('success', 'Borrador eliminado correctamente.');
    }

    public function post(PostJournalEntryRequest $request, JournalEntry $journalEntry)
    {
        $this->ensureEntryBelongsToActiveCompany($journalEntry);

        $journalEntry = $this->journalEntryService->post(
            $journalEntry,
            (int) session('company_id'),
            $request->user()->id
        );

        return redirect()
            ->route('journal-entries.show', $journalEntry)
            ->with('success', 'Póliza contabilizada correctamente.');
    }

    public function void(VoidJournalEntryRequest $request, JournalEntry $journalEntry)
    {
        $this->ensureEntryBelongsToActiveCompany($journalEntry);

        $journalEntry = $this->journalEntryService->void(
            $journalEntry,
            $request->validated('void_reason'),
            (int) session('company_id'),
            $request->user()->id
        );

        return redirect()
            ->route('journal-entries.show', $journalEntry)
            ->with('success', 'Póliza anulada correctamente.');
    }

    private function getFormOptions(int $companyId): array
    {
        $periods = AccountingPeriod::where('company_id', $companyId)
            ->where('status', AccountingPeriodStatus::OPEN->value)
            ->orderByDesc('start_date')
            ->get();

        $accounts = Account::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('allows_entries', true)
            ->orderBy('code')
            ->get();

        return [$periods, $accounts];
    }

    private function ensureEntryBelongsToActiveCompany(JournalEntry $journalEntry): void
    {
        if ((int) $journalEntry->company_id !== (int) session('company_id')) {
            abort(403);
        }
    }
}
