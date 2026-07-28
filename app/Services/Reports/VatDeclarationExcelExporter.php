<?php

namespace App\Services\Reports;

use App\Enums\FiscalDocumentDirection;
use App\Models\Company;
use App\Models\User;
use App\Models\VatDeclaration;
use Carbon\CarbonInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class VatDeclarationExcelExporter
{
    public function download(VatDeclaration $declaration, Company $company, User $user, CarbonInterface $generatedAt): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'declaracion-iva-');
        abort_if($path === false, 500, 'No fue posible preparar la exportación de Excel.');

        try {
            $options = new Options;
            $options->DEFAULT_ROW_STYLE->setFontName('Arial')->setFontSize(10);
            $writer = new Writer($options);
            $writer->openToFile($path);
            try {
                $this->write($writer, $declaration, $company, $user, $generatedAt);
            } finally {
                $writer->close();
            }
        } catch (Throwable $exception) {
            @unlink($path);
            throw $exception;
        }

        return response()->download($path, ReportFilename::make('declaracion-iva', $company, $generatedAt, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function write(Writer $writer, VatDeclaration $declaration, Company $company, User $user, CarbonInterface $generatedAt): void
    {
        $header = (new Style)->setFontBold()->setFontColor(Color::WHITE)->setBackgroundColor(Color::DARK_BLUE)->setCellAlignment(CellAlignment::CENTER);
        $label = (new Style)->setFontBold()->setFontColor(Color::DARK_BLUE);
        $money = (new Style)->setFormat('Q #,##0.00')->setCellAlignment(CellAlignment::RIGHT);

        $summary = $writer->getCurrentSheet();
        $summary->setName('Resumen');
        foreach ([
            ['Empresa', $company->legal_name ?: $company->name],
            ['NIT', $company->tax_id],
            ['Período', $declaration->accountingPeriod->name],
            ['Rango', $declaration->accountingPeriod->start_date->format('d/m/Y').' al '.$declaration->accountingPeriod->end_date->format('d/m/Y')],
            ['Régimen', 'IVA General'],
            ['Estado', $declaration->status->label()],
            ['Generado', $generatedAt->format('d/m/Y H:i:s')],
            ['Usuario', $user->name],
            ['Cantidad de documentos', $declaration->documents->where('included', true)->count()],
        ] as $row) {
            $writer->addRow(Row::fromValues($row, $label));
        }
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Concepto', 'Monto'], $header));
        foreach ($this->summaryAmounts($declaration) as $name => $amount) {
            $writer->addRow(Row::fromValuesWithStyles([$name, (float) $amount], null, [1 => $money]));
        }

        $this->documentSheet($writer, 'Ventas incluidas', $declaration->documents->where('included', true)->where('direction', FiscalDocumentDirection::SALE), $header, $money);
        $this->documentSheet($writer, 'Compras incluidas', $declaration->documents->where('included', true)->where('direction', FiscalDocumentDirection::PURCHASE), $header, $money);
        $this->documentSheet($writer, 'Documentos excluidos', $declaration->documents->where('included', false), $header, $money);

        $writer->addNewSheetAndMakeItCurrent()->setName('Advertencias');
        $writer->addRow(Row::fromValues(['Documento', 'Motivo'], $header));
        foreach ($declaration->documents->where('included', false) as $snapshot) {
            $writer->addRow(Row::fromValues([$snapshot->fiscal_document_id, $snapshot->exclusion_reason]));
        }
    }

    private function documentSheet(Writer $writer, string $name, iterable $documents, Style $header, Style $money): void
    {
        $writer->addNewSheetAndMakeItCurrent()->setName($name);
        $writer->addRow(Row::fromValues([
            'Fecha', 'Tipo', 'Serie', 'Número', 'UUID', 'NIT', 'Tercero', 'Estado revisión',
            'Estado fiscal', 'Base', 'Exento', 'No afecto', 'IVA', 'IVA acreditable',
            'IVA no acreditable', 'Otros', 'Total', 'Efecto', 'Incluido', 'Motivo exclusión',
        ], $header));
        foreach ($documents as $snapshot) {
            $source = $snapshot->source_snapshot ?? [];
            $writer->addRow(Row::fromValuesWithStyles([
                $source['document_date'] ?? null,
                $snapshot->document_type->label(),
                $source['series'] ?? null,
                $source['document_number'] ?? null,
                $source['authorization_uuid'] ?? null,
                $source['third_party_tax_id'] ?? null,
                $source['third_party_name'] ?? null,
                $snapshot->review_status,
                $snapshot->fiscal_status->value,
                (float) $snapshot->taxable_amount,
                (float) $snapshot->exempt_amount,
                (float) $snapshot->non_taxable_amount,
                (float) $snapshot->vat_amount,
                (float) $snapshot->creditable_vat_amount,
                (float) $snapshot->non_creditable_vat_amount,
                (float) $snapshot->other_taxes_amount,
                (float) $snapshot->total_amount,
                $snapshot->effect,
                $snapshot->included ? 'Sí' : 'No',
                $snapshot->exclusion_reason,
            ], null, [9 => $money, 10 => $money, 11 => $money, 12 => $money, 13 => $money, 14 => $money, 15 => $money, 16 => $money]));
        }
    }

    private function summaryAmounts(VatDeclaration $declaration): array
    {
        return [
            'Ventas gravadas' => $declaration->sales_taxable_amount,
            'IVA débito de ventas' => $declaration->sales_vat_amount,
            'Compras gravadas' => $declaration->purchase_taxable_amount,
            'IVA total en compras' => $declaration->purchase_vat_amount,
            'IVA acreditable' => $declaration->purchase_creditable_vat_amount,
            'IVA no acreditable' => $declaration->purchase_non_creditable_vat_amount,
            'Saldo anterior' => $declaration->previous_credit_balance,
            'Débito fiscal neto' => $declaration->net_vat_debit,
            'Crédito fiscal neto' => $declaration->net_vat_credit,
            'IVA estimado por pagar' => $declaration->vat_payable,
            'Crédito para siguiente período' => $declaration->credit_balance,
        ];
    }
}
