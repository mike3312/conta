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
use App\Http\Controllers\FelReclassificationController;
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
    Route::get('accounting/daily-book/export/pdf', [DailyBookController::class, 'exportPdf'])
        ->name('accounting.daily-book.export.pdf');
    Route::get('accounting/daily-book/export/excel', [DailyBookController::class, 'exportExcel'])
        ->name('accounting.daily-book.export.excel');
    Route::get('accounting/general-ledger', [GeneralLedgerController::class, 'index'])
        ->name('accounting.general-ledger.index');
    Route::get('accounting/general-ledger/export/pdf', [GeneralLedgerController::class, 'exportPdf'])
        ->name('accounting.general-ledger.export.pdf');
    Route::get('accounting/general-ledger/export/excel', [GeneralLedgerController::class, 'exportExcel'])
        ->name('accounting.general-ledger.export.excel');
    Route::get('accounting/trial-balance', [TrialBalanceController::class, 'index'])
        ->name('accounting.trial-balance.index');
    Route::get('accounting/trial-balance/export/pdf', [TrialBalanceController::class, 'exportPdf'])
        ->name('accounting.trial-balance.export.pdf');
    Route::get('accounting/trial-balance/export/excel', [TrialBalanceController::class, 'exportExcel'])
        ->name('accounting.trial-balance.export.excel');
    Route::get('accounting/income-statement', [IncomeStatementController::class, 'index'])
        ->name('accounting.income-statement.index');
    Route::get('accounting/income-statement/export/pdf', [IncomeStatementController::class, 'exportPdf'])
        ->name('accounting.income-statement.export.pdf');
    Route::get('accounting/income-statement/export/excel', [IncomeStatementController::class, 'exportExcel'])
        ->name('accounting.income-statement.export.excel');
    Route::get('accounting/balance-sheet', [BalanceSheetController::class, 'index'])
        ->name('accounting.balance-sheet.index');
    Route::get('accounting/balance-sheet/export/pdf', [BalanceSheetController::class, 'exportPdf'])
        ->name('accounting.balance-sheet.export.pdf');
    Route::get('accounting/balance-sheet/export/excel', [BalanceSheetController::class, 'exportExcel'])
        ->name('accounting.balance-sheet.export.excel');

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
    Route::get('fel/reclassification', [FelReclassificationController::class, 'index'])->name('fel.reclassification.index');
    Route::get('fel/reclassification/preview', [FelReclassificationController::class, 'showPreview'])->name('fel.reclassification.preview.show');
    Route::post('fel/reclassification/preview', [FelReclassificationController::class, 'preview'])->name('fel.reclassification.preview');
    Route::post('fel/reclassification/execute', [FelReclassificationController::class, 'execute'])->name('fel.reclassification.execute');
    Route::get('fel/reclassification/result', [FelReclassificationController::class, 'result'])->name('fel.reclassification.result');

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
