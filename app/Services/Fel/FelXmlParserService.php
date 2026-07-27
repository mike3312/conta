<?php

namespace App\Services\Fel;

use App\Exceptions\FelImportException;
use App\Support\FelAuthorizationNormalizer;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class FelXmlParserService
{
    private readonly FelAuthorizationNormalizer $authorizations;

    public function __construct(?FelAuthorizationNormalizer $authorizations = null)
    {
        $this->authorizations = $authorizations ?? new FelAuthorizationNormalizer;
    }

    public function parse(string $path): array
    {
        if (! class_exists(DOMDocument::class) || ! function_exists('libxml_use_internal_errors')) {
            throw new FelImportException('FEL-XML-READ', 'xml_environment', 'El servidor no dispone de DOMDocument/libxml para leer XML.');
        }
        if (! is_file($path) || ! is_readable($path)) {
            throw new FelImportException('FEL-XML-READ', 'xml_read', 'El XML no está disponible o no puede leerse.');
        }
        if (filesize($path) > config('fel.max_xml_bytes')) {
            throw new FelImportException('FEL-XML-READ', 'xml_read', 'El XML excede el límite permitido.');
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new FelImportException('FEL-XML-READ', 'xml_read', 'No se pudo leer el contenido del XML.');
        }
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $contents)) {
            throw new FelImportException('FEL-XML-PARSE', 'xml_security', 'El XML contiene una declaración DTD o entidad no permitida.');
        }

        $dom = new DOMDocument;
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;
        $previous = libxml_use_internal_errors(true);
        try {
            if (! $dom->loadXML($contents, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT)) {
                throw new FelImportException('FEL-XML-PARSE', 'xml_parse', 'El archivo no contiene XML válido.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($dom);
        $first = fn (string $name, ?DOMNode $context = null) => $xpath->query('.//*[local-name()="'.$name.'"]', $context ?? $dom)->item(0);
        $attribute = fn (?DOMNode $node, string $name) => $node instanceof DOMElement ? trim($node->getAttribute($name)) ?: null : null;
        $text = fn (?DOMNode $node) => $node ? trim($node->textContent) ?: null : null;

        $cancellation = $xpath->query('//*[local-name()="DatosGenerales" and @NumeroDocumentoAAnular]')->item(0);
        if ($cancellation instanceof DOMElement) {
            return [
                'is_cancellation' => true,
                'cancellation' => [
                    'authorization_uuid' => $attribute($cancellation, 'NumeroDocumentoAAnular'),
                    'issued_at' => $attribute($cancellation, 'FechaEmisionDocumentoAnular'),
                    'voided_at' => $attribute($cancellation, 'FechaHoraAnulacion'),
                    'reason' => $attribute($cancellation, 'MotivoAnulacion'),
                ],
            ];
        }

        $general = $first('DatosGenerales');
        $issuer = $first('Emisor');
        $receiver = $first('Receptor');
        $certification = $first('Certificacion');
        $authorization = $first('NumeroAutorizacion', $certification);

        $items = [];
        foreach ($xpath->query('//*[local-name()="Items"]/*[local-name()="Item"]') as $itemNode) {
            $lineTaxes = [];
            foreach ($xpath->query('.//*[local-name()="Impuestos"]/*[local-name()="Impuesto"]', $itemNode) as $taxNode) {
                $lineTaxes[] = $this->tax($xpath, $taxNode, 'ITEM');
            }
            $items[] = [
                'line_number' => (int) ($attribute($itemNode, 'NumeroLinea') ?? count($items) + 1),
                'goods_or_service' => $attribute($itemNode, 'BienOServicio'),
                'quantity' => $this->number($text($first('Cantidad', $itemNode))),
                'description' => $text($first('Descripcion', $itemNode)) ?? '',
                'unit_price' => $this->number($text($first('PrecioUnitario', $itemNode))),
                'gross_price' => $this->number($text($first('Precio', $itemNode))),
                'discount' => $this->number($text($first('Descuento', $itemNode))),
                'other_discount' => 0,
                'taxable_amount' => array_sum(array_map(
                    fn (array $tax) => $this->normalize($tax['tax_name']) === 'IVA' ? $tax['taxable_amount'] : 0,
                    $lineTaxes,
                )),
                'tax_amount' => array_sum(array_column($lineTaxes, 'tax_amount')),
                'total' => $this->number($text($first('Total', $itemNode))),
                'taxes' => $lineTaxes,
            ];
        }

        $taxes = [];
        foreach ($xpath->query('//*[local-name()="Totales"]/*[local-name()="TotalImpuestos"]/*[local-name()="TotalImpuesto"]') as $taxNode) {
            $taxes[] = [
                'tax_name' => $attribute($taxNode, 'NombreCorto') ?? 'DESCONOCIDO',
                'tax_code' => null,
                'taxable_unit_code' => null,
                'taxable_amount' => 0,
                'tax_amount' => $this->number($attribute($taxNode, 'TotalMontoImpuesto')),
                'source_level' => 'DOCUMENT',
            ];
        }

        $phrases = [];
        foreach ($xpath->query('//*[local-name()="Frases"]/*[local-name()="Frase"]') as $node) {
            $phrases[] = $this->attributes($node);
        }
        $complements = [];
        foreach ($xpath->query('//*[local-name()="Complementos"]/*[local-name()="Complemento"]') as $node) {
            $complements[] = ['attributes' => $this->attributes($node), 'content' => $this->nodeArray($node)];
        }

        $uuid = $this->authorizations->normalize($text($authorization));
        if (! $uuid) {
            throw new FelImportException('FEL-XML-UUID', 'xml_uuid', 'El XML no contiene un número de autorización válido.');
        }

        $result = [
            'general' => ['dte_type' => $attribute($general, 'Tipo'), 'currency' => $attribute($general, 'CodigoMoneda') ?: 'GTQ', 'issued_at' => $attribute($general, 'FechaHoraEmision'), 'xml_version' => $dom->documentElement?->getAttribute('Version') ?: null, 'fel_version' => $dom->documentElement?->namespaceURI],
            'issuer' => ['tax_id' => $attribute($issuer, 'NITEmisor'), 'name' => $attribute($issuer, 'NombreEmisor'), 'commercial_name' => $attribute($issuer, 'NombreComercial'), 'tax_regime' => $attribute($issuer, 'AfiliacionIVA'), 'establishment_code' => $attribute($issuer, 'CodigoEstablecimiento'), ...$this->address($xpath, $first('DireccionEmisor', $issuer))],
            'receiver' => ['tax_id' => $attribute($receiver, 'IDReceptor'), 'name' => $attribute($receiver, 'NombreReceptor'), 'address' => $text($first('Direccion', $first('DireccionReceptor', $receiver)))],
            'items' => $items,
            'taxes' => $taxes,
            'totals' => ['subtotal' => array_sum(array_column($items, 'gross_price')), 'discount_total' => array_sum(array_column($items, 'discount')), 'taxable_total' => array_sum(array_column($items, 'taxable_amount')), 'tax_total' => array_sum(array_map(fn ($tax) => $this->normalize($tax['tax_name']) === 'IVA' ? $tax['tax_amount'] : 0, $taxes)), 'other_tax_total' => array_sum(array_map(fn ($tax) => $this->normalize($tax['tax_name']) === 'IVA' ? 0 : $tax['tax_amount'], $taxes)), 'grand_total' => $this->number($text($first('GranTotal')))],
            'phrases' => $phrases,
            'complements' => $complements,
            'certification' => ['authorization_uuid' => $uuid, 'series' => $attribute($authorization, 'Serie'), 'document_number' => $attribute($authorization, 'Numero'), 'certifier_tax_id' => $text($first('NITCertificador', $certification)), 'certifier_name' => $text($first('NombreCertificador', $certification)), 'certified_at' => $text($first('FechaHoraCertificacion', $certification))],
            'signatures' => ['count' => $xpath->query('//*[local-name()="Signature"]')->length],
            'metadata' => [],
        ];

        if (! data_get($result, 'general.issued_at') || ! data_get($result, 'general.dte_type') || ! is_numeric(data_get($result, 'totals.grand_total'))) {
            throw new FelImportException('FEL-XML-DATA', 'xml_data', 'El XML no contiene fecha, tipo DTE o gran total válidos.');
        }

        unset($xpath, $dom, $contents);

        return $result;
    }

    private function tax(DOMXPath $xpath, DOMNode $node, string $level): array
    {
        $value = fn (string $name) => trim($xpath->query('.//*[local-name()="'.$name.'"]', $node)->item(0)?->textContent ?? '');

        return ['tax_name' => $value('NombreCorto') ?: 'DESCONOCIDO', 'tax_code' => $value('CodigoUnidadGravable') ?: null, 'taxable_unit_code' => $value('CodigoUnidadGravable') ?: null, 'taxable_amount' => $this->number($value('MontoGravable')), 'tax_amount' => $this->number($value('MontoImpuesto')), 'source_level' => $level];
    }

    private function address(DOMXPath $xpath, ?DOMNode $node): array
    {
        $value = fn (string $name) => trim($xpath->query('.//*[local-name()="'.$name.'"]', $node)->item(0)?->textContent ?? '') ?: null;

        return ['address' => $value('Direccion'), 'municipality' => $value('Municipio'), 'department' => $value('Departamento'), 'country' => $value('Pais')];
    }

    private function attributes(DOMNode $node): array
    {
        $data = [];
        foreach ($node->attributes ?? [] as $attribute) {
            $data[$attribute->nodeName] = $attribute->nodeValue;
        }

        return $data;
    }

    private function nodeArray(DOMNode $node): array
    {
        $data = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $data[$child->localName] = $child->childElementCount ? $this->nodeArray($child) : trim($child->textContent);
            }
        }

        return $data;
    }

    private function number(?string $value): float
    {
        return is_numeric(str_replace(',', '', (string) $value)) ? (float) str_replace(',', '', (string) $value) : 0.0;
    }

    private function normalize(string $value): string
    {
        return strtoupper(trim(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value));
    }
}
