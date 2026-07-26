<?php

namespace App\Services\Fel;

use DOMDocument;
use DOMXPath;
use Generator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;
use XMLReader;
use ZipArchive;

class FelExcelParserService
{
    private const REQUIRED = ['authorization_uuid', 'issued_at', 'dte_type', 'issuer_tax_id', 'issuer_name', 'receiver_tax_id', 'grand_total'];

    private const ALIASES = [
        'numero de autorizacion' => 'authorization_uuid', 'uuid' => 'authorization_uuid', 'fecha de emision' => 'issued_at', 'tipo de dte' => 'dte_type', 'serie' => 'series', 'numero del dte' => 'document_number', 'clasificacion' => 'classification_text',
        'numero autorizacion' => 'authorization_uuid', 'no de autorizacion' => 'authorization_uuid', 'no autorizacion' => 'authorization_uuid', 'fecha emision' => 'issued_at', 'tipo dte' => 'dte_type',
        'nit del emisor' => 'issuer_tax_id', 'nombre completo del emisor' => 'issuer_name', 'codigo de establecimiento' => 'issuer_establishment_code', 'nombre del establecimiento' => 'issuer_commercial_name', 'id del receptor' => 'receiver_tax_id', 'nit del receptor' => 'receiver_tax_id', 'nombre completo del receptor' => 'receiver_name',
        'nit del certificador' => 'certifier_tax_id', 'nombre del certificador' => 'certifier_name', 'estado' => 'fiscal_status_text', 'moneda' => 'currency', 'gran total' => 'grand_total', 'marca de anulado' => 'voided_mark', 'fecha de anulacion' => 'voided_at',
        'iva' => 'tax:IVA', 'petroleo' => 'tax:PETROLEO', 'turismo hospedaje' => 'tax:TURISMO HOSPEDAJE', 'turismo pasajes' => 'tax:TURISMO PASAJES', 'timbre de prensa' => 'tax:TIMBRE DE PRENSA', 'bomberos' => 'tax:BOMBEROS', 'tasa municipal' => 'tax:TASA MUNICIPAL', 'bebidas alcoholicas' => 'tax:BEBIDAS ALCOHOLICAS', 'tabaco' => 'tax:TABACO', 'cemento' => 'tax:CEMENTO', 'bebidas no alcoholicas' => 'tax:BEBIDAS NO ALCOHOLICAS', 'tarifa portuaria' => 'tax:TARIFA PORTUARIA',
    ];

    public function rows(string $path, string $extension): Generator
    {
        try {
            $extension = strtolower($extension);
            $rows = match ($extension) {
                'csv' => $this->csvRows($path), 'xlsx' => $this->xlsxRows($path), 'xls' => $this->legacyRows($path), default => throw new InvalidArgumentException('Formato tabular no admitido.')
            };
            yield from $this->normalizeRows($rows);
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('No se pudo leer el archivo tabular.', previous: $exception);
        }
    }

    private function normalizeRows(iterable $rows): Generator
    {
        $headers = null;
        $sheet = null;
        $recognized = [];
        foreach ($rows as $entry) {
            $values = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $entry['values']);
            if ($sheet !== $entry['sheet']) {
                $headers = null;
                $sheet = $entry['sheet'];
            }
            if ($headers === null) {
                $candidate = [];
                foreach ($values as $index => $value) {
                    if (($key = $this->alias((string) $value))) {
                        $candidate[$index] = $key;
                    }
                }
                if (count($candidate) > count($recognized)) {
                    $recognized = $candidate;
                }
                if (count(array_intersect(self::REQUIRED, $candidate)) >= count(self::REQUIRED) - 1) {
                    $this->assertRequired($candidate);
                    $headers = $candidate;
                }

                continue;
            }
            if (collect($values)->filter(fn ($value) => $value !== '' && $value !== null)->isEmpty()) {
                continue;
            }
            $data = ['taxes' => [], 'raw_data' => []];
            foreach ($headers as $index => $key) {
                $value = $values[$index] ?? null;
                if (str_starts_with($key, 'tax:')) {
                    if ($value !== null && $value !== '' && is_numeric(str_replace(',', '', $value))) {
                        $data['taxes'][] = ['tax_name' => substr($key, 4), 'tax_amount' => (float) str_replace(',', '', $value), 'taxable_amount' => 0, 'source_level' => 'EXCEL_SUMMARY'];
                    }
                } else {
                    $data[$key] = $value;
                }
            }
            $data['authorization_uuid'] = $data['authorization_uuid'] ?? null;
            $data['issued_at'] = $this->date($data['issued_at'] ?? null);
            $data['grand_total'] = $this->number($data['grand_total'] ?? null);
            yield ['row_number' => $entry['row'], 'sheet_name' => $entry['sheet'], 'data' => $data];
        }
        if ($headers === null) {
            $missing = array_diff(self::REQUIRED, $recognized);
            throw new InvalidArgumentException('No se reconoció una fila de encabezados FEL. Faltan columnas obligatorias: '.implode(', ', $missing).'.');
        }
    }

    private function csvRows(string $path): Generator
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            throw new InvalidArgumentException('No se pudo leer el CSV.');
        }
        $sample = fgets($handle) ?: '';
        rewind($handle);
        $delimiter = substr_count($sample, ';') > substr_count($sample, ',') ? ';' : ',';
        $row = 0;
        try {
            while (($values = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                yield ['row' => ++$row, 'sheet' => 'CSV', 'values' => $values];
            }
        } finally {
            fclose($handle);
        }
    }

    private function xlsxRows(string $path): Generator
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('El archivo XLSX no es válido.');
        }

        if ($zip->locateName('[Content_Types].xml') === false) {
            $zip->close();
            throw new InvalidArgumentException('El archivo XLSX no es válido.');
        }

        try {
            $uncompressedBytes = 0;
            if ($zip->numFiles > config('fel.max_zip_entries')) {
                throw new InvalidArgumentException('El XLSX contiene demasiadas entradas internas.');
            }
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $uncompressedBytes += (int) (($zip->statIndex($index)['size'] ?? 0));
            }
            if ($uncompressedBytes > config('fel.max_zip_uncompressed_bytes')) {
                throw new InvalidArgumentException('El XLSX excede el tamaño descomprimido permitido.');
            }
            $shared = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $dom = $this->xmlDocument($xml, 'El archivo XLSX contiene cadenas compartidas inválidas.');
                $xp = new DOMXPath($dom);
                foreach ($xp->query('//*[local-name()="si"]') as $node) {
                    $shared[] = trim($node->textContent);
                }
            }
            $sheets = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (is_string($name) && preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                    $sheets[] = $name;
                }
            }
            sort($sheets, SORT_NATURAL);
            foreach ($sheets as $sheetIndex => $name) {
                $temp = tempnam(sys_get_temp_dir(), 'fel-xlsx-');
                $contents = $zip->getFromName($name);
                if ($temp === false || $contents === false || preg_match('/<!DOCTYPE|<!ENTITY/i', $contents) || file_put_contents($temp, $contents) === false) {
                    if (is_string($temp)) {
                        @unlink($temp);
                    }

                    throw new InvalidArgumentException('El archivo XLSX contiene una hoja inválida.');
                }
                $reader = new XMLReader;
                if (! $reader->open($temp, null, LIBXML_NONET | LIBXML_COMPACT)) {
                    @unlink($temp);
                    throw new InvalidArgumentException('No se pudo leer una hoja del archivo XLSX.');
                }
                try {
                    while ($reader->read()) {
                        if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'row') {
                            $node = $reader->expand();
                            if (! $node) {
                                continue;
                            }
                            $values = [];
                            foreach ($node->childNodes as $cell) {
                                if ($cell->localName === 'c') {
                                    $ref = $cell->attributes?->getNamedItem('r')?->nodeValue ?? '';
                                    preg_match('/^[A-Z]+/', $ref, $m);
                                    $index = $this->columnIndex($m[0] ?? 'A');
                                    $type = $cell->attributes?->getNamedItem('t')?->nodeValue;
                                    $value = '';
                                    foreach ($cell->childNodes as $child) {
                                        if (in_array($child->localName, ['v', 'is'], true)) {
                                            $value = trim($child->textContent);
                                        }
                                    } $values[$index] = $type === 's' ? ($shared[(int) $value] ?? '') : $value;
                                }
                            } yield ['row' => (int) ($node->attributes?->getNamedItem('r')?->nodeValue ?? 0), 'sheet' => 'Hoja '.($sheetIndex + 1), 'values' => $values ? array_replace(array_fill(0, max(array_keys($values)) + 1, ''), $values) : []];
                        }
                    }
                } finally {
                    $reader->close();
                    @unlink($temp);
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function legacyRows(string $path): Generator
    {
        $head = file_get_contents($path, false, null, 0, 512) ?: '';
        if (! preg_match('/<\?xml|<html|<table/i', $head)) {
            throw new InvalidArgumentException('El formato XLS binario requiere maatwebsite/excel y la extensión PHP GD. Guarde el archivo como XLSX o CSV.');
        }
        $dom = new DOMDocument;
        @$dom->loadHTMLFile($path, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xp = new DOMXPath($dom);
        $row = 0;
        foreach ($xp->query('//*[local-name()="tr" or local-name()="Row"]') as $node) {
            $values = [];
            foreach ($xp->query('./*[local-name()="td" or local-name()="th" or local-name()="Cell"]', $node) as $cell) {
                $values[] = trim($cell->textContent);
            } yield ['row' => ++$row, 'sheet' => 'Hoja 1', 'values' => $values];
        }
    }

    private function xmlDocument(string $contents, string $message): DOMDocument
    {
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $contents)) {
            throw new InvalidArgumentException($message);
        }

        $document = new DOMDocument;
        $document->resolveExternals = false;
        $document->substituteEntities = false;
        $previous = libxml_use_internal_errors(true);
        try {
            if (! $document->loadXML($contents, LIBXML_NONET | LIBXML_COMPACT)) {
                throw new InvalidArgumentException($message);
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $document;
    }

    private function alias(string $value): ?string
    {
        $key = $this->normalize($value);

        return self::ALIASES[$key] ?? null;
    }

    private function assertRequired(array $headers): void
    {
        $missing = array_diff(self::REQUIRED, $headers);
        if ($missing) {
            throw new InvalidArgumentException('Faltan columnas obligatorias: '.implode(', ', $missing).'.');
        }
    }

    private function normalize(string $value): string
    {
        return (string) Str::of($value)
            ->ascii('es')
            ->lower()
            ->replaceMatches('/[^a-z0-9 ]/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim();
    }

    private function number(mixed $value): ?float
    {
        $value = str_replace([',', 'Q', '$', ' '], '', (string) $value);

        return is_numeric($value) ? (float) $value : null;
    }

    private function date(mixed $value): mixed
    {
        if (is_numeric($value) && (float) $value > 1000) {
            return gmdate('Y-m-d H:i:s', ((int) $value - 25569) * 86400);
        }

        return $value;
    }

    private function columnIndex(string $letters): int
    {
        $number = 0;
        foreach (str_split($letters) as $letter) {
            $number = $number * 26 + ord($letter) - 64;
        }

        return max(0, $number - 1);
    }
}
