<?php

namespace App\Services\Fel;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Throwable;
use ZipArchive;

class FelZipExtractorService
{
    public function process(string $zipPath, callable $callback): mixed
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CHECKCONS) !== true) {
            throw new InvalidArgumentException('El archivo ZIP no es válido.');
        }
        $directory = storage_path('app/private/fel-temp/'.bin2hex(random_bytes(16)));
        $xmlFiles = [];
        $totalSize = 0;
        try {
            File::ensureDirectoryExists($directory, 0700, true);
            if ($zip->numFiles > config('fel.max_zip_entries')) {
                throw new InvalidArgumentException('El ZIP contiene demasiados archivos.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (! is_array($stat)) {
                    throw new InvalidArgumentException('No se pudo leer una entrada del ZIP.');
                }
                $name = str_replace('\\', '/', $stat['name'] ?? '');
                if ($name === '' || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name)) {
                    throw new InvalidArgumentException('El ZIP contiene una ruta no segura.');
                }
                $totalSize += (int) ($stat['size'] ?? 0);
                if ($totalSize > config('fel.max_zip_uncompressed_bytes')) {
                    throw new InvalidArgumentException('El ZIP excede el tamaño descomprimido permitido.');
                }
                if (! str_ends_with(strtolower($name), '.xml')) {
                    continue;
                }
                if (count($xmlFiles) >= config('fel.max_documents')) {
                    throw new InvalidArgumentException('El ZIP supera la cantidad máxima de XML.');
                }
                $stream = $zip->getStream($stat['name']);
                if (! $stream) {
                    throw new InvalidArgumentException('No se pudo leer un XML contenido en el ZIP.');
                }
                $target = $directory.'/'.bin2hex(random_bytes(8)).'.xml';
                $out = fopen($target, 'wb');
                if (! $out) {
                    fclose($stream);
                    throw new InvalidArgumentException('No se pudo preparar un XML contenido en el ZIP.');
                }
                try {
                    if (stream_copy_to_stream($stream, $out, config('fel.max_xml_bytes') + 1) === false) {
                        throw new InvalidArgumentException('No se pudo extraer un XML contenido en el ZIP.');
                    }
                } finally {
                    fclose($out);
                    fclose($stream);
                }
                if (filesize($target) > config('fel.max_xml_bytes')) {
                    @unlink($target);
                    throw new InvalidArgumentException('Un XML del ZIP excede el límite permitido.');
                }
                $xmlFiles[] = ['path' => $target, 'name' => basename($name)];
            }
            if (! $xmlFiles) {
                throw new InvalidArgumentException('El archivo ZIP no contiene documentos XML.');
            }

            return $callback($xmlFiles);
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('No se pudo procesar el archivo ZIP.', previous: $exception);
        } finally {
            $zip->close();
            File::deleteDirectory($directory);
        }
    }
}
