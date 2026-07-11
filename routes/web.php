<?php

use App\Http\Controllers\Accounting\AccountController;
use App\Http\Controllers\Accounting\AccountingPeriodController;
use App\Http\Controllers\Accounting\DailyBookController;
use App\Http\Controllers\Accounting\GeneralLedgerController;
use App\Http\Controllers\Accounting\JournalEntryController;
use App\Http\Controllers\Accounting\TrialBalanceController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::post('/switch-company', [CompanySwitchController::class, 'update'])
        ->name('companies.switch');

    Route::resource('companies', CompanyController::class);
    Route::resource('accounts', AccountController::class);
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
