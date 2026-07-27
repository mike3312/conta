<?php

namespace App\Console\Commands;

use App\Models\FelDocument;
use App\Services\Fiscal\FelFiscalDocumentSyncService;
use App\Support\FelAuthorizationNormalizer;
use Illuminate\Console\Command;

class SyncFelFiscalDocuments extends Command
{
    protected $signature = 'fiscal:sync-fel
        {--company= : Limitar a una empresa}
        {--document= : ID o UUID de un documento FEL}
        {--only-missing : Procesar únicamente FEL sin vínculo fiscal}
        {--force-update : Recalcular aunque no se detecten cambios}
        {--dry-run : Simular sin modificar datos}
        {--chunk=100 : Cantidad de documentos por bloque}';

    protected $description = 'Sincroniza documentos FEL existentes con los libros fiscales sin crear pólizas';

    public function handle(FelFiscalDocumentSyncService $sync, FelAuthorizationNormalizer $authorizations): int
    {
        $chunk = filter_var($this->option('chunk'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($chunk === false) {
            $this->error('La opción --chunk debe estar entre 1 y 1000.');

            return self::INVALID;
        }
        $companyId = $this->positiveOption('company');
        if ($this->option('company') !== null && $companyId === null) {
            $this->error('La opción --company debe ser un ID positivo.');

            return self::INVALID;
        }

        $query = FelDocument::query()->with('company')->orderBy('id');
        $query->when($companyId, fn ($query) => $query->where('company_id', $companyId));
        $query->when($this->option('only-missing'), fn ($query) => $query->whereDoesntHave('fiscalDocument'));
        if ($document = trim((string) $this->option('document'))) {
            if (ctype_digit($document)) {
                $query->whereKey((int) $document);
            } elseif ($uuid = $authorizations->normalize($document)) {
                $authorizations->whereMatches($query, 'authorization_uuid', $uuid);
            } else {
                $this->error('La opción --document debe ser un ID o UUID válido.');

                return self::INVALID;
            }
        }

        $found = (clone $query)->count();
        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && $this->input->isInteractive() && ! $this->confirm("Se sincronizarán {$found} documentos FEL. ¿Desea continuar?")) {
            $this->warn('Sincronización cancelada.');

            return self::SUCCESS;
        }

        $summary = array_fill_keys(['CREATED', 'UPDATED', 'UNCHANGED', 'OBSERVED', 'CONFLICT', 'ERROR', 'SKIPPED'], 0);
        $query->chunkById($chunk, function ($documents) use ($sync, $dryRun, &$summary) {
            foreach ($documents as $document) {
                try {
                    $result = $sync->syncFromFel($document, dryRun: $dryRun, forceUpdate: (bool) $this->option('force-update'));
                    $summary[$result->action] = ($summary[$result->action] ?? 0) + 1;
                    if ($result->warnings || $result->errors) {
                        $this->line(sprintf(
                            '#%d %s · %s: %s',
                            $document->id,
                            $document->authorization_uuid,
                            $result->action,
                            implode(' ', [...$result->warnings, ...$result->errors]),
                        ));
                    }
                } catch (\Throwable $exception) {
                    $summary['ERROR']++;
                    report($exception);
                    $this->error("#{$document->id}: ocurrió un error técnico; revise el log de la aplicación.");
                }
            }
        }, 'id');

        $this->table(['Resultado', 'Cantidad'], [
            ['Documentos FEL encontrados', $found],
            ['Documentos fiscales creados', $summary['CREATED']],
            ['Documentos actualizados', $summary['UPDATED']],
            ['Sin cambios', $summary['UNCHANGED']],
            ['Observados', $summary['OBSERVED'] + $summary['SKIPPED']],
            ['Conflictos', $summary['CONFLICT']],
            ['Errores técnicos', $summary['ERROR']],
            ['Modo', $dryRun ? 'DRY-RUN (sin escritura)' : 'APLICADO'],
        ]);

        return self::SUCCESS;
    }

    private function positiveOption(string $name): ?int
    {
        $value = $this->option($name);
        if ($value === null || $value === '') {
            return null;
        }
        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $validated === false ? null : $validated;
    }
}
