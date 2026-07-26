<?php

namespace App\Services\Fel;

use App\Enums\FelDocumentDataLevel;
use App\Enums\FelDocumentSourceType;
use App\Enums\FelDocumentStatus;
use App\Enums\FelFiscalStatus;
use App\Enums\FelImportBatchStatus;
use App\Enums\FelImportRowStatus;
use App\Enums\FelTaxSourceLevel;
use App\Models\Company;
use App\Models\FelDocument;
use App\Models\FelImportBatch;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

class FelDocumentImportService
{
    public function __construct(private FelXmlParserService $xml, private FelExcelParserService $excel, private FelZipExtractorService $zip, private FelClassificationService $classifier) {}

    public function import(UploadedFile $file, Company $company, User $user): FelImportBatch
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $source = match ($extension) {
            'xml' => FelDocumentSourceType::XML, 'zip' => FelDocumentSourceType::ZIP_XML, 'csv' => FelDocumentSourceType::CSV, 'xls', 'xlsx' => FelDocumentSourceType::EXCEL, default => throw new InvalidArgumentException('Formato no admitido.')
        };

        Log::info('FEL import started', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'source_type' => $source->value,
            'size_bytes' => $file->getSize(),
        ]);

        $stored = null;
        try {
            $stored = $file->storeAs($this->directory($company), bin2hex(random_bytes(12)).'.'.$extension, config('fel.disk'));
            if (! $stored) {
                throw new InvalidArgumentException('No se pudo guardar el archivo de forma privada.');
            }

            $batch = FelImportBatch::create([
                'tenant_id' => $company->tenant_id,
                'company_id' => $company->id,
                'source_type' => $source,
                'original_filename' => basename($file->getClientOriginalName()),
                'stored_path' => $stored,
                'original_mime_type' => $file->getMimeType(),
                'original_size' => $file->getSize(),
                'status' => FelImportBatchStatus::PROCESSING,
                'imported_by' => $user->id,
                'started_at' => now(),
            ]);
        } catch (Throwable $exception) {
            if ($stored) {
                Storage::disk(config('fel.disk'))->delete($stored);
            }

            $this->logTechnicalError('FEL import could not be initialized', $exception, [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'source_type' => $source->value,
            ]);

            throw new InvalidArgumentException('No se pudo iniciar la importación FEL. Intente nuevamente.', previous: $exception);
        }

        try {
            match ($extension) {
                'xml' => $this->processXml($batch, $file->getRealPath(), $file->getClientOriginalName(), $source, $company, $user),
                'zip' => $this->processZip($batch, $file->getRealPath(), $source, $company, $user),
                'xls', 'xlsx', 'csv' => $this->processTable($batch, $file->getRealPath(), $file->getClientOriginalName(), $extension, $source, $company, $user),
            };
            $batch->refresh();
            $batch->update(['status' => $batch->failed_records ? FelImportBatchStatus::COMPLETED_WITH_ERRORS : FelImportBatchStatus::COMPLETED, 'finished_at' => now()]);
        } catch (Throwable $exception) {
            $this->logTechnicalError('FEL import failed', $exception, $this->batchContext($batch));

            try {
                $batch->update(['status' => FelImportBatchStatus::FAILED, 'failed_records' => max(1, $batch->failed_records), 'finished_at' => now()]);
                if ($batch->total_records === 0) {
                    $this->row($batch, ['source_filename' => basename($file->getClientOriginalName()), 'status' => FelImportRowStatus::FAILED, 'message' => $this->safeMessage($exception)]);
                }
            } catch (Throwable $statusException) {
                $this->logTechnicalError('FEL failed batch status could not be persisted', $statusException, $this->batchContext($batch));
            }
        }

        $batch = $batch->fresh() ?? $batch;
        Log::info('FEL import finished', [
            ...$this->batchContext($batch),
            'status' => $batch->status->value,
            'total_records' => $batch->total_records,
            'successful_records' => $batch->successful_records,
            'duplicate_records' => $batch->duplicate_records,
            'failed_records' => $batch->failed_records,
        ]);

        return $batch;
    }

    private function processZip(FelImportBatch $batch, string $path, FelDocumentSourceType $source, Company $company, User $user): void
    {
        try {
            $this->zip->process($path, function (array $files) use ($batch, $source, $company, $user) {
                $batch->update(['total_files' => count($files)]);
                foreach ($files as $entry) {
                    $this->processXml($batch, $entry['path'], $entry['name'], $source, $company, $user);
                }
            });

            Log::info('FEL ZIP processed', [
                ...$this->batchContext($batch),
                'xml_files' => ($batch->fresh() ?? $batch)->total_files,
            ]);
        } catch (InvalidArgumentException $exception) {
            Log::warning('FEL ZIP parser rejected archive', [
                ...$this->batchContext($batch),
                'reason' => $exception->getMessage(),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            $this->logTechnicalError('FEL ZIP parser failed', $exception, $this->batchContext($batch));

            throw new InvalidArgumentException('No se pudo procesar el archivo ZIP.', previous: $exception);
        }
    }

    private function processXml(FelImportBatch $batch, string $path, string $name, FelDocumentSourceType $source, Company $company, User $user): void
    {
        $batch->increment('total_records');
        if ($batch->total_files === 0) {
            $batch->update(['total_files' => 1]);
        }
        try {
            $data = $this->xml->parse($path);
        } catch (InvalidArgumentException $exception) {
            Log::warning('FEL XML parser error', [
                ...$this->batchContext($batch),
                'exception' => $exception::class,
                'reason' => $this->safeMessage($exception),
            ]);
            $this->recordFailure($batch, $name, $exception);

            return;
        } catch (Throwable $exception) {
            $this->logTechnicalError('FEL XML parser failed', $exception, $this->batchContext($batch));
            $this->recordFailure($batch, $name, $exception);

            return;
        }

        try {
            $this->persist($batch, $data, $source, FelDocumentDataLevel::FULL_DETAIL, $company, $user, ['source_filename' => basename($name)], $path);
            Log::info('FEL XML processed', $this->batchContext($batch));
        } catch (Throwable $exception) {
            $this->logTechnicalError('FEL XML persistence failed', $exception, $this->batchContext($batch));
            $this->recordFailure($batch, $name, $exception);
        }
    }

    private function processTable(FelImportBatch $batch, string $path, string $name, string $extension, FelDocumentSourceType $source, Company $company, User $user): void
    {
        $batch->update(['total_files' => 1]);
        try {
            foreach ($this->excel->rows($path, $extension) as $entry) {
                if ($batch->total_records >= config('fel.max_documents')) {
                    throw new InvalidArgumentException('El archivo supera la cantidad máxima de documentos.');
                }
                $batch->increment('total_records');
                try {
                    $this->persist($batch, $this->tableData($entry['data']), $source, FelDocumentDataLevel::SUMMARY, $company, $user, ['source_filename' => basename($name), 'sheet_name' => $entry['sheet_name'], 'row_number' => $entry['row_number'], 'raw_data' => $this->safeRaw($entry['data'])]);
                } catch (Throwable $exception) {
                    $this->logTechnicalError('FEL spreadsheet row persistence failed', $exception, [
                        ...$this->batchContext($batch),
                        'row_number' => $entry['row_number'],
                    ]);
                    $this->recordFailure($batch, $name, $exception, [
                        'sheet_name' => $entry['sheet_name'],
                        'row_number' => $entry['row_number'],
                    ]);
                }
            }
        } catch (InvalidArgumentException $exception) {
            Log::warning('FEL spreadsheet parser rejected file', [
                ...$this->batchContext($batch),
                'format' => $extension,
                'reason' => $exception->getMessage(),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            $this->logTechnicalError('FEL spreadsheet parser failed', $exception, [
                ...$this->batchContext($batch),
                'format' => $extension,
            ]);

            throw new InvalidArgumentException('No se pudo procesar el archivo tabular.', previous: $exception);
        }

        Log::info('FEL Excel/CSV processed', [
            ...$this->batchContext($batch),
            'format' => $extension,
            'total_records' => ($batch->fresh() ?? $batch)->total_records,
        ]);
    }

    private function persist(FelImportBatch $batch, array $data, FelDocumentSourceType $source, FelDocumentDataLevel $level, Company $company, User $user, array $row, ?string $xmlPath = null): void
    {
        $originalUuid = data_get($data, 'certification.authorization_uuid');
        $uuid = $this->uuid($originalUuid);
        if (! $uuid) {
            throw new InvalidArgumentException('El documento no contiene un número de autorización válido.');
        }
        if (! data_get($data, 'general.issued_at') || ! data_get($data, 'general.dte_type') || ! is_numeric(data_get($data, 'totals.grand_total'))) {
            throw new InvalidArgumentException('El documento no contiene fecha, tipo DTE o gran total válidos.');
        }
        $existing = FelDocument::where('company_id', $company->id)->where('authorization_uuid', $uuid)->first();
        if ($existing && ($existing->data_level === FelDocumentDataLevel::FULL_DETAIL || $level === FelDocumentDataLevel::SUMMARY)) {
            $batch->increment('duplicate_records');
            $this->row($batch, [...$row, 'authorization_uuid' => $uuid, 'status' => FelImportRowStatus::DUPLICATE, 'message' => 'El documento ya existe con el mismo o mayor nivel de detalle.', 'fel_document_id' => $existing->id]);
            Log::info('FEL document duplicate detected', [
                ...$this->batchContext($batch),
                'document_id' => $existing->id,
                'data_level' => $level->value,
            ]);

            return;
        }
        $classification = $this->classifier->classify($data, $company->tax_id);
        $voided = $this->isVoided($data);
        $status = $voided ? FelDocumentStatus::OBSERVED : FelDocumentStatus::PENDING;
        if ($classification['missing_company_tax_id']) {
            $status = FelDocumentStatus::OBSERVED;
        }
        $storedXml = null;
        if ($xmlPath) {
            $storedXml = $this->directory($company).'/'.$uuid.'.xml';
            $contents = file_get_contents($xmlPath);
            if ($contents === false || ! Storage::disk(config('fel.disk'))->put($storedXml, $contents)) {
                throw new InvalidArgumentException('No se pudo conservar el XML original.');
            }
        }
        try {
            [$documentId, $result] = DB::transaction(function () use ($existing, $batch, $data, $source, $level, $company, $user, $uuid, $originalUuid, $classification, $voided, $status, $row, $storedXml, $xmlPath) {
                $attributes = $this->attributes($data, $batch, $source, $level, $company, $user, $uuid, $originalUuid, $classification, $voided, $status, $storedXml, $xmlPath);
                if ($existing) {
                    $existing->update($attributes);
                    $existing->items()->delete();
                    $existing->taxes()->delete();
                    $document = $existing;
                    $result = FelImportRowStatus::ENRICHED;
                    $batch->increment('enriched_records');
                } else {
                    $document = FelDocument::create($attributes);
                    $result = $status === FelDocumentStatus::OBSERVED ? FelImportRowStatus::OBSERVED : FelImportRowStatus::IMPORTED;
                    $batch->increment('successful_records');
                }
                $this->details($document, $data);
                if ($status === FelDocumentStatus::OBSERVED) {
                    $batch->increment('observed_records');
                }
                if ($classification['classification']->value === 'FUEL') {
                    $batch->increment('fuel_records');
                }
                if ($voided) {
                    $batch->increment('voided_records');
                }
                $this->row($batch, [...$row, 'authorization_uuid' => $uuid, 'status' => $result, 'message' => $existing ? 'El documento resumido fue enriquecido con el XML.' : ($status === FelDocumentStatus::OBSERVED ? 'Importado con observaciones para revisión.' : 'Documento importado.'), 'fel_document_id' => $document->id]);

                return [$document->id, $result];
            });
        } catch (Throwable $exception) {
            if ($storedXml) {
                Storage::disk(config('fel.disk'))->delete($storedXml);
            }

            throw $exception;
        }

        if ($result === FelImportRowStatus::ENRICHED) {
            Log::info('FEL document enriched', [
                ...$this->batchContext($batch),
                'document_id' => $documentId,
            ]);
        }
    }

    private function attributes(array $data, FelImportBatch $batch, FelDocumentSourceType $source, FelDocumentDataLevel $level, Company $company, User $user, string $uuid, ?string $originalUuid, array $classification, bool $voided, FelDocumentStatus $status, ?string $storedXml, ?string $xmlPath): array
    {
        return ['tenant_id' => $company->tenant_id, 'company_id' => $company->id, 'fel_import_batch_id' => $batch->id, 'authorization_uuid' => $uuid, 'authorization_uuid_original' => $originalUuid, 'series' => data_get($data, 'certification.series'), 'document_number' => data_get($data, 'certification.document_number'), 'dte_type' => data_get($data, 'general.dte_type'), 'currency' => data_get($data, 'general.currency', 'GTQ'), 'source_type' => $source, 'data_level' => $level, 'operation_type' => $classification['operation_type'], 'classification' => $classification['classification'], 'status' => $status, 'fiscal_status' => $voided ? FelFiscalStatus::VOIDED : FelFiscalStatus::ACTIVE, 'issued_at' => data_get($data, 'general.issued_at'), 'voided_at' => data_get($data, 'metadata.voided_at'), 'issuer_tax_id' => data_get($data, 'issuer.tax_id'), 'issuer_name' => data_get($data, 'issuer.name'), 'issuer_commercial_name' => data_get($data, 'issuer.commercial_name'), 'issuer_tax_regime' => data_get($data, 'issuer.tax_regime'), 'issuer_establishment_code' => data_get($data, 'issuer.establishment_code'), 'issuer_address' => data_get($data, 'issuer.address'), 'issuer_municipality' => data_get($data, 'issuer.municipality'), 'issuer_department' => data_get($data, 'issuer.department'), 'issuer_country' => data_get($data, 'issuer.country'), 'receiver_tax_id' => data_get($data, 'receiver.tax_id'), 'receiver_name' => data_get($data, 'receiver.name'), 'receiver_address' => data_get($data, 'receiver.address'), 'certifier_tax_id' => data_get($data, 'certification.certifier_tax_id'), 'certifier_name' => data_get($data, 'certification.certifier_name'), 'certified_at' => data_get($data, 'certification.certified_at'), ...$data['totals'], 'requires_tax_review' => $classification['requires_tax_review'] || $voided, 'requires_accounting_review' => true, 'xml_version' => data_get($data, 'general.xml_version'), 'fel_version' => data_get($data, 'general.fel_version'), 'signature_count' => data_get($data, 'signatures.count', 0), 'phrases' => $data['phrases'] ?: null, 'complements' => $data['complements'] ?: null, 'metadata' => [...($data['metadata'] ?? []), 'company_tax_id_missing' => $classification['missing_company_tax_id']], 'xml_path' => $storedXml, 'xml_hash' => $xmlPath ? hash_file('sha256', $xmlPath) : null, 'imported_by' => $user->id, 'journal_entry_id' => null];
    }

    private function details(FelDocument $document, array $data): void
    {
        foreach ($data['items'] ?? [] as $item) {
            $taxes = $item['taxes'] ?? [];
            unset($item['taxes']);
            $model = $document->items()->create($item);
            foreach ($taxes as $tax) {
                $document->taxes()->create([...$tax, 'fel_document_item_id' => $model->id, 'tax_name_normalized' => $this->classifier->normalizeTaxName($tax['tax_name']), 'source_level' => FelTaxSourceLevel::ITEM]);
            }
        }
        foreach ($data['taxes'] ?? [] as $tax) {
            $document->taxes()->create([...$tax, 'tax_name_normalized' => $this->classifier->normalizeTaxName($tax['tax_name']), 'source_level' => FelTaxSourceLevel::from($tax['source_level'] ?? 'DOCUMENT')]);
        }
    }

    private function tableData(array $row): array
    {
        $voided = $this->truthy($row['voided_mark'] ?? null) || str_contains(strtoupper((string) ($row['fiscal_status_text'] ?? '')), 'ANUL');

        return ['general' => ['dte_type' => $row['dte_type'] ?? null, 'currency' => ($row['currency'] ?? null) ?: 'GTQ', 'issued_at' => $row['issued_at'] ?? null, 'xml_version' => null, 'fel_version' => null], 'issuer' => ['tax_id' => $row['issuer_tax_id'] ?? null, 'name' => $row['issuer_name'] ?? null, 'commercial_name' => $row['issuer_commercial_name'] ?? null, 'establishment_code' => $row['issuer_establishment_code'] ?? null], 'receiver' => ['tax_id' => $row['receiver_tax_id'] ?? null, 'name' => $row['receiver_name'] ?? null], 'items' => [], 'taxes' => $row['taxes'] ?? [], 'totals' => ['subtotal' => null, 'discount_total' => 0, 'taxable_total' => null, 'tax_total' => collect($row['taxes'] ?? [])->where('tax_name', 'IVA')->sum('tax_amount'), 'other_tax_total' => collect($row['taxes'] ?? [])->where('tax_name', '!=', 'IVA')->sum('tax_amount'), 'grand_total' => $row['grand_total'] ?? null], 'phrases' => [], 'complements' => [], 'certification' => ['authorization_uuid' => $row['authorization_uuid'] ?? null, 'series' => $row['series'] ?? null, 'document_number' => $row['document_number'] ?? null, 'certifier_tax_id' => $row['certifier_tax_id'] ?? null, 'certifier_name' => $row['certifier_name'] ?? null, 'certified_at' => null], 'signatures' => ['count' => 0], 'metadata' => ['voided' => $voided, 'voided_at' => $row['voided_at'] ?? null, 'fiscal_status_original' => $row['fiscal_status_text'] ?? null]];
    }

    private function row(FelImportBatch $batch, array $data): void
    {
        $batch->rows()->create($data);
    }

    private function recordFailure(FelImportBatch $batch, string $sourceName, Throwable $exception, array $row = []): void
    {
        $batch->increment('failed_records');
        $this->row($batch, [
            'source_filename' => basename($sourceName),
            ...$row,
            'status' => FelImportRowStatus::FAILED,
            'message' => $this->safeMessage($exception),
        ]);
    }

    private function batchContext(FelImportBatch $batch): array
    {
        return [
            'batch_id' => $batch->id,
            'company_id' => $batch->company_id,
            'source_type' => $batch->source_type->value,
        ];
    }

    private function logTechnicalError(string $message, Throwable $exception, array $context): void
    {
        $context['exception'] = $exception::class;

        if ($exception instanceof QueryException) {
            $context['sql_state'] = $exception->errorInfo[0] ?? null;
            $context['database_error_code'] = $exception->errorInfo[1] ?? null;
            Log::error('FEL database error: '.$message, $context);

            return;
        }

        Log::error($message, $context);
    }

    private function uuid(?string $value): ?string
    {
        $value = strtoupper(preg_replace('/\s+/', '', (string) $value));

        return preg_match('/^[A-Z0-9][A-Z0-9-]{7,99}$/', $value) ? $value : null;
    }

    private function isVoided(array $data): bool
    {
        return (bool) data_get($data, 'metadata.voided');
    }

    private function truthy(mixed $value): bool
    {
        return in_array(strtoupper(trim((string) $value)), ['1', 'SI', 'SÍ', 'TRUE', 'X', 'ANULADO'], true);
    }

    private function directory(Company $company): string
    {
        return sprintf('fel/companies/%s/%s', $company->uuid, now()->format('Y/m'));
    }

    private function safeRaw(array $row): array
    {
        return collect($row)
            ->except(['raw_data', 'issuer_tax_id', 'issuer_name', 'issuer_commercial_name', 'receiver_tax_id', 'receiver_name', 'certifier_tax_id', 'certifier_name'])
            ->map(fn ($value) => is_scalar($value) ? mb_substr((string) $value, 0, 500) : $value)
            ->all();
    }

    private function safeMessage(Throwable $e): string
    {
        return $e instanceof InvalidArgumentException ? $e->getMessage() : 'No se pudo procesar este documento. Consulte el registro técnico.';
    }
}
