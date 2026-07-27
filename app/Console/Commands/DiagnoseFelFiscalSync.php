<?php

namespace App\Console\Commands;

use App\Models\FelDocument;
use App\Models\FiscalDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseFelFiscalSync extends Command
{
    protected $signature = 'fiscal:diagnose-fel-sync {--company= : Limitar a una empresa}';

    protected $description = 'Diagnostica vínculos y diferencias entre FEL y libros fiscales sin modificar datos';

    public function handle(): int
    {
        $companyId = $this->option('company');
        if ($companyId !== null && filter_var($companyId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $this->error('La opción --company debe ser un ID positivo.');

            return self::INVALID;
        }

        $fel = FelDocument::query()->when($companyId, fn ($query) => $query->where('company_id', (int) $companyId));
        $fiscal = FiscalDocument::query()->when($companyId, fn ($query) => $query->where('company_id', (int) $companyId));
        $closedPeriods = (clone $fel)->whereExists(function ($query) {
            $query->selectRaw('1')->from('accounting_periods')
                ->whereColumn('accounting_periods.company_id', 'fel_documents.company_id')
                ->whereColumn('accounting_periods.start_date', '<=', 'fel_documents.issued_at')
                ->whereColumn('accounting_periods.end_date', '>=', 'fel_documents.issued_at')
                ->where('accounting_periods.status', 'closed');
        })->count();
        $normalizedDuplicates = DB::query()->fromSub(
            (clone $fel)->selectRaw("company_id, REPLACE(REPLACE(REPLACE(REPLACE(UPPER(authorization_uuid), '-', ''), ' ', ''), '{', ''), '}', '') normalized_uuid")
                ->groupBy('company_id', 'normalized_uuid')
                ->havingRaw('COUNT(*) > 1'),
            'normalized_duplicates',
        )->count();
        $amountDifferences = DB::table('fiscal_documents as fiscal')
            ->join('fel_documents as fel', 'fel.id', '=', 'fiscal.fel_document_id')
            ->when($companyId, fn ($query) => $query->where('fel.company_id', (int) $companyId))
            ->whereRaw('ABS(ROUND(fel.grand_total, 2) - fiscal.total_amount) > 0.02')
            ->count();

        $this->table(['Diagnóstico', 'Cantidad'], [
            ['FEL sin documento fiscal', (clone $fel)->whereDoesntHave('fiscalDocument')->count()],
            ['Documentos fiscales FEL sin vínculo', (clone $fiscal)->where('source', 'FEL')->whereNull('fel_document_id')->count()],
            ['UUID FEL duplicados normalizados', $normalizedDuplicates],
            ['Vínculos entre empresas diferentes', DB::table('fiscal_documents as fiscal')->join('fel_documents as fel', 'fel.id', '=', 'fiscal.fel_document_id')->whereColumn('fiscal.company_id', '!=', 'fel.company_id')->count()],
            ['FEL con operación desconocida', (clone $fel)->where('operation_type', 'UNKNOWN')->count()],
            ['Monedas extranjeras', (clone $fel)->where('currency', '!=', 'GTQ')->count()],
            ['FEL en períodos cerrados', $closedPeriods],
            ['Diferencias entre total FEL y fiscal', $amountDifferences],
            ['FEL anulados con documento fiscal activo', (clone $fel)->where('fiscal_status', 'VOIDED')->whereHas('fiscalDocument', fn ($query) => $query->where('status', 'ACTIVE'))->count()],
            ['Vínculos huérfanos preservados', (clone $fiscal)->where('source', 'FEL')->whereNull('fel_document_id')->count()],
        ]);

        $this->info('Diagnóstico finalizado sin modificar datos.');

        return self::SUCCESS;
    }
}
