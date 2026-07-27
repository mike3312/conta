<?php

namespace App\Console\Commands;

use App\Models\FelDocument;
use App\Services\Fel\FelReclassificationService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ReclassifyFelDocuments extends Command
{
    protected $signature = 'fel:reclassify
        {--company= : ID de la empresa que se volverá a cargar desde la base de datos}
        {--only-unknown : Procesar únicamente documentos con operación UNKNOWN}
        {--dry-run : Mostrar cambios sin escribir en la base de datos}
        {--include-reviewed : Incluir documentos que ya tienen revisión humana}';

    protected $description = 'Reclasifica documentos FEL usando el NIT actual de la empresa, sin alterar revisión ni contenido fiscal';

    public function handle(FelReclassificationService $service): int
    {
        $companyId = filter_var($this->option('company'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($companyId === false) {
            $this->error('La opción --company es obligatoria y debe ser un ID positivo.');

            return self::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->components->info($dryRun ? 'Simulando reclasificación FEL' : 'Ejecutando reclasificación FEL');

        try {
            $summary = $service->run(
                companyId: $companyId,
                onlyUnknown: (bool) $this->option('only-unknown'),
                dryRun: $dryRun,
                includeReviewed: (bool) $this->option('include-reviewed'),
                onChange: function (FelDocument $document, array $changes, bool $simulation): void {
                    $prefix = $simulation ? 'SIMULAR' : 'ACTUALIZAR';
                    $this->line(sprintf(
                        '%s #%d %s: %s/%s -> %s/%s',
                        $prefix,
                        $document->id,
                        $document->authorization_uuid,
                        $document->operation_type->value,
                        $document->classification->value,
                        $changes['operation_type']->value,
                        $changes['classification']->value,
                    ));
                },
            );
        } catch (ModelNotFoundException) {
            $this->error("No existe la empresa {$companyId}.");

            return self::FAILURE;
        }

        $this->table(['Dato', 'Valor'], [
            ['Empresa', $summary['company_id'].' · '.$summary['company_name']],
            ['NIT actual utilizado', $summary['company_tax_id'] ?: 'Sin NIT'],
            ['Candidatos', $summary['candidates']],
            ['Con cambios', $summary['changed']],
            ['Sin cambios', $summary['unchanged']],
            ['Revisados omitidos', $summary['reviewed_skipped']],
            ['Modo', $dryRun ? 'DRY-RUN (sin escritura)' : 'APLICADO'],
        ]);

        return self::SUCCESS;
    }
}
