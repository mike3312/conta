<?php

use App\Http\Controllers\Accounting\AccountController;
use App\Http\Controllers\Accounting\AccountingPeriodController;
use App\Http\Controllers\Accounting\BalanceSheetController;
use App\Http\Controllers\Accounting\DailyBookController;
use App\Http\Controllers\Accounting\GeneralLedgerController;
use App\Http\Controllers\Accounting\IncomeStatementController;
use App\Http\Controllers\Accounting\JournalEntryController;
use App\Http\Controllers\Accounting\TrialBalanceController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FelDocumentController;
use App\Http\Controllers\FelImportController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware(['auth', 'verified', 'company'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::post('/switch-company', [CompanySwitchController::class, 'update'])
        ->name('companies.switch');

    Route::resource('companies', CompanyController::class);
    Route::resource('accounts', AccountController::class);
    Route::post('accounting-periods/{accountingPeriod}/close', [AccountingPeriodController::class, 'close'])
        ->name('accounting-periods.close');
    Route::resource('accounting-periods', AccountingPeriodController::class)
        ->except('show');
    Route::post('journal-entries/{journalEntry}/post', [JournalEntryController::class, 'post'])
        ->name('journal-entries.post');
    Route::post('journal-entries/{journalEntry}/void', [JournalEntryController::class, 'void'])
        ->name('journal-entries.void');
    Route::resource('journal-entries', JournalEntryController::class);
    Route::get('accounting/daily-book', [DailyBookController::class, 'index'])
        ->name('accounting.daily-book.index');
    Route::get('accounting/general-ledger', [GeneralLedgerController::class, 'index'])
        ->name('accounting.general-ledger.index');
    Route::get('accounting/trial-balance', [TrialBalanceController::class, 'index'])
        ->name('accounting.trial-balance.index');
    Route::get('accounting/income-statement', [IncomeStatementController::class, 'index'])
        ->name('accounting.income-statement.index');
    Route::get('accounting/balance-sheet', [BalanceSheetController::class, 'index'])
        ->name('accounting.balance-sheet.index');

    Route::get('fel-imports', [FelImportController::class, 'index'])->name('fel-imports.index');
    Route::get('fel-imports/create', [FelImportController::class, 'create'])->name('fel-imports.create');
    Route::post('fel-imports', [FelImportController::class, 'store'])->name('fel-imports.store');
    Route::get('fel-imports/{felImportBatch}', [FelImportController::class, 'show'])->name('fel-imports.show');
    Route::get('fel-documents', [FelDocumentController::class, 'index'])->name('fel-documents.index');
    Route::get('fel-documents/{felDocument}', [FelDocumentController::class, 'show'])->name('fel-documents.show');
    Route::post('fel-documents/{felDocument}/approve', [FelDocumentController::class, 'approve'])->name('fel-documents.approve');
    Route::post('fel-documents/{felDocument}/observe', [FelDocumentController::class, 'observe'])->name('fel-documents.observe');
    Route::post('fel-documents/{felDocument}/reject', [FelDocumentController::class, 'reject'])->name('fel-documents.reject');
    Route::get('fel-documents/{felDocument}/xml', [FelDocumentController::class, 'downloadXml'])->name('fel-documents.xml');

});

Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');

});

require __DIR__.'/auth.php';
