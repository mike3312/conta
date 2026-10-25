<?php

namespace Database\Seeders;

use App\Enums\AccountNature;
use App\Enums\AccountType;
use App\Enums\AccountingPeriodStatus;
use App\Enums\CompanyStatus;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\AccountCatalogService;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::orderBy('id')->first();

        if (! $user) {
            $this->failSeeder('Crea primero un usuario en /register y vuelve a correr el seeder.');
        }

        $company = Company::where('tax_id', '99999999-9')->first();
        $companyWasCreated = ! $company;

        if ($companyWasCreated) {
            $company = Company::create([
                'tenant_id' => $user->tenant_id,
                'name' => 'Comercializadora Demo',
                'legal_name' => 'Comercializadora Demo',
                'tax_id' => '99999999-9',
                'email' => 'contacto@comercializadora-demo.invalid',
                'currency' => 'GTQ',
                'timezone' => 'America/Guatemala',
                'country' => 'Guatemala',
                'status' => CompanyStatus::ACTIVE,
            ]);

            app(AccountCatalogService::class)->createDefaultCatalog($company);
        }

        if (! $company->users()->whereKey($user->id)->exists()) {
            $company->users()->attach($user->id, [
                'is_owner' => true,
                'is_active' => true,
                'joined_at' => now(),
            ]);
        }

        $extraAccounts = $this->ensureExtraAccounts($company);
        $period = $this->ensurePeriod($company);
        $journalEntries = $this->ensureJournalEntries($company, $period, $user);

        $this->printSummary(
            $company,
            $period,
            $companyWasCreated,
            $extraAccounts,
            $journalEntries,
        );
    }

    /**
     * @return array<string, array{account: Account, created: bool}>
     */
    private function ensureExtraAccounts(Company $company): array
    {
        $definitions = [
            ['code' => '1.1.05', 'name' => 'IVA crédito fiscal', 'type' => AccountType::ASSET, 'nature' => AccountNature::DEBIT, 'parent' => '1.1'],
            ['code' => '2.1.03', 'name' => 'IVA débito fiscal', 'type' => AccountType::LIABILITY, 'nature' => AccountNature::CREDIT, 'parent' => '2.1'],
            ['code' => '5.1.04', 'name' => 'Compras', 'type' => AccountType::EXPENSE, 'nature' => AccountNature::DEBIT, 'parent' => '5.1'],
            ['code' => '5.1.05', 'name' => 'Gasto de energía eléctrica', 'type' => AccountType::EXPENSE, 'nature' => AccountNature::DEBIT, 'parent' => '5.1'],
        ];

        $accounts = [];

        foreach ($definitions as $definition) {
            $parent = Account::where('company_id', $company->id)
                ->where('code', $definition['parent'])
                ->first();

            if (! $parent) {
                $this->failSeeder("No se encontró la cuenta padre {$definition['parent']} en la empresa demo.");
            }

            $account = Account::where('company_id', $company->id)
                ->where('code', $definition['code'])
                ->first();
            $created = ! $account;

            $account = Account::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'code' => $definition['code'],
                ],
                [
                    'parent_id' => $parent->id,
                    'name' => $definition['name'],
                    'account_type' => $definition['type']->value,
                    'nature' => $definition['nature']->value,
                    'allows_entries' => true,
                    'level' => $parent->level + 1,
                    'is_active' => true,
                ],
            );

            $accounts[$definition['code']] = [
                'account' => $account,
                'created' => $created,
            ];
        }

        return $accounts;
    }

    private function ensurePeriod(Company $company): AccountingPeriod
    {
        $period = AccountingPeriod::where('company_id', $company->id)
            ->where('name', 'agosto 2026')
            ->first();

        if ($period?->status === AccountingPeriodStatus::CLOSED) {
            $this->failSeeder('El período agosto 2026 está cerrado; no se reabrirá.');
        }

        $overlap = AccountingPeriod::where('company_id', $company->id)
            ->when($period, fn ($query) => $query->where('id', '!=', $period->id))
            ->whereDate('start_date', '<=', '2026-08-31')
            ->whereDate('end_date', '>=', '2026-08-01')
            ->first();

        if ($overlap) {
            $this->failSeeder("El período {$overlap->name} se traslapa con agosto 2026.");
        }

        return $period ?? AccountingPeriod::create([
            'company_id' => $company->id,
            'name' => 'agosto 2026',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => AccountingPeriodStatus::OPEN,
        ]);
    }

    /**
     * @return array<int, array{description: string, entry: JournalEntry|null, skipped: bool}>
     */
    private function ensureJournalEntries(
        Company $company,
        AccountingPeriod $period,
        User $user,
    ): array {
        $codes = [
            'caja' => '1.1.01',
            'bancos' => '1.1.02',
            'capital' => '3.1.01',
            'ventas' => '4.1.01',
            'iva_credito' => '1.1.05',
            'iva_debito' => '2.1.03',
            'compras' => '5.1.04',
            'energia' => '5.1.05',
        ];
        $accounts = [];

        foreach ($codes as $name => $code) {
            $account = Account::where('company_id', $company->id)->where('code', $code)->first();

            if (! $account) {
                $this->failSeeder("No se encontró la cuenta {$code} requerida por la demostración.");
            }

            $accounts[$name] = $account;
        }

        $entries = [
            [
                'date' => '2026-08-03',
                'description' => 'Aporte inicial de capital',
                'lines' => [
                    [$accounts['bancos'], '20000.00', '0.00'],
                    [$accounts['capital'], '0.00', '20000.00'],
                ],
            ],
            [
                'date' => '2026-08-10',
                'description' => 'Venta gravada',
                'lines' => [
                    [$accounts['caja'], '5600.00', '0.00'],
                    [$accounts['ventas'], '0.00', '5000.00'],
                    [$accounts['iva_debito'], '0.00', '600.00'],
                ],
            ],
            [
                'date' => '2026-08-17',
                'description' => 'Compra pagada desde bancos',
                'lines' => [
                    [$accounts['compras'], '2000.00', '0.00'],
                    [$accounts['iva_credito'], '240.00', '0.00'],
                    [$accounts['bancos'], '0.00', '2240.00'],
                ],
            ],
            [
                'date' => '2026-08-24',
                'description' => 'Pago de energía eléctrica',
                'lines' => [
                    [$accounts['energia'], '500.00', '0.00'],
                    [$accounts['bancos'], '0.00', '500.00'],
                ],
            ],
        ];
        $service = app(JournalEntryService::class);
        $results = [];

        foreach ($entries as $definition) {
            $existing = JournalEntry::where('company_id', $company->id)
                ->where('accounting_period_id', $period->id)
                ->where('description', $definition['description'])
                ->where('status', JournalEntryStatus::POSTED)
                ->first();

            if ($existing) {
                $this->command?->info("Póliza saltada: {$definition['description']} (número {$existing->number}).");
                $results[] = ['description' => $definition['description'], 'entry' => $existing, 'skipped' => true];
                continue;
            }

            $draft = $service->createDraft([
                'accounting_period_id' => $period->id,
                'entry_date' => $definition['date'],
                'description' => $definition['description'],
                'lines' => array_map(
                    fn (array $line) => [
                        'account_id' => $line[0]->id,
                        'debit' => $line[1],
                        'credit' => $line[2],
                        'description' => null,
                    ],
                    $definition['lines'],
                ),
            ], $company->id, $user->id);
            $posted = $service->post($draft, $company->id, $user->id);

            $this->command?->info("Póliza creada: {$definition['description']} (número {$posted->number}).");
            $results[] = ['description' => $definition['description'], 'entry' => $posted, 'skipped' => false];
        }

        return $results;
    }

    /** @param array<string, array{account: Account, created: bool}> $extraAccounts */
    private function printSummary(
        Company $company,
        AccountingPeriod $period,
        bool $companyWasCreated,
        array $extraAccounts,
        array $journalEntries,
    ): void {
        $this->command?->newLine();
        $this->command?->info('Resumen del seeder demo');
        $this->command?->line('Empresa: '.$company->name.' ('.($companyWasCreated ? 'creada' : 'encontrada').')');
        $this->command?->line('Período: '.$period->name.' ('.$period->status->value.')');
        $this->command?->line('Cuentas extra: '.implode(', ', array_map(
            fn (array $item) => $item['account']->code.' ('.($item['created'] ? 'creada' : 'encontrada').')',
            $extraAccounts,
        )));
        $this->command?->line('Pólizas: '.implode(', ', array_map(
            fn (array $item) => ($item['skipped'] ? 'saltada ' : 'creada ').$item['entry']->number,
            $journalEntries,
        )));
        $this->command?->line('Valores esperados: Bancos Q17,260.00; Caja General Q5,600.00; IVA crédito Q240.00; IVA débito Q600.00; Capital Social Q20,000.00; Ingresos Q5,000.00; Gastos Q2,500.00; Activos Q23,100.00; Utilidad Q2,500.00; diferencia Q0.00.');
    }

    private function failSeeder(string $message): never
    {
        $this->command?->error($message);

        throw new RuntimeException($message);
    }
}
