<?php

namespace App\Services\Reports;

use App\Enums\FiscalDocumentDirection;
use App\Models\Company;
use App\Models\User;
use Carbon\CarbonInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class FiscalBookExcelExporter
{
    private const HEADER_ROW = 8;

    public function download(Company $company, User $user, FiscalDocumentDirection $direction, array $report, CarbonInterface $generatedAt): BinaryFileResponse
    {
        $isPurchase = $direction === FiscalDocumentDirection::PURCHASE;
        $title = $isPurchase ? 'Libro de Compras' : 'Libro de Ventas';
        $slug = $isPurchase ? 'libro-compras' : 'libro-ventas';
        $temporaryPath = tempnam(sys_get_temp_dir(), $slug.'-');
        abort_if($temporaryPath === false, 500, 'No fue posible preparar la exportación de Excel.');

        try {
            $options = new Options;
            $options->DEFAULT_ROW_STYLE->setFontName('Arial')->setFontSize(10);
            $writer = new Writer($options);
            $writer->openToFile($temporaryPath);
            try {
                $this->write($writer, $company, $user, $title, $report, $generatedAt);
            } finally {
                $writer->close();
            }
        } catch (Throwable $exception) {
            @unlink($temporaryPath);
            throw $exception;
        }

        return response()->download($temporaryPath, ReportFilename::make($slug, $company, $generatedAt, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function write(Writer $writer, Company $company, User $user, string $title, array $report, CarbonInterface $generatedAt): void
    {
        $sheet = $writer->getCurrentSheet();
        $sheet->setName(mb_substr($title, 0, 31));
        $sheet->setSheetView((new SheetView)->setFreezeRow(self::HEADER_ROW + 1));
        $sheet->setPrintTitleRows(self::HEADER_ROW.':'.self::HEADER_ROW);
        $sheet->setColumnWidth(15, 1, 2, 3, 4, 6, 8, 9, 10, 11, 12, 13);
        $sheet->setColumnWidth(28, 5, 7);
        $label = (new Style)->setFontBold()->setFontColor(Color::DARK_BLUE);
        $titleStyle = (new Style)->setFontBold()->setFontSize(16)->setFontColor(Color::DARK_BLUE);
        $header = (new Style)->setFontBold()->setFontColor(Color::WHITE)->setBackgroundColor(Color::DARK_BLUE)->setCellAlignment(CellAlignment::CENTER);
        $date = (new Style)->setFormat('dd/mm/yyyy');
        $money = (new Style)->setFormat('Q #,##0.00')->setCellAlignment(CellAlignment::RIGHT);
        $total = (new Style)->setFontBold()->setBackgroundColor('D9EAF7');
        $totalMoney = (new Style)->setFontBold()->setBackgroundColor('D9EAF7')->setFormat('Q #,##0.00')->setCellAlignment(CellAlignment::RIGHT);

        foreach ([['Empresa', $company->name], ['NIT', $company->tax_id], [$title], ['Período / rango', $report['periodDescription']], ['Generado', $generatedAt->format('d/m/Y H:i:s')], ['Usuario', $user->name], []] as $index => $values) {
            $writer->addRow(Row::fromValues($values, $index === 2 ? $titleStyle : ($index < 2 || $index === 3 || $index === 4 || $index === 5 ? $label : null)));
        }
        $writer->addRow(Row::fromValues(['Fecha', 'Tipo', 'Serie', 'Número', 'UUID', 'NIT', 'Tercero', 'Categoría', 'Base imponible', 'Exento', 'No afecto', 'IVA', 'Otros impuestos', 'Total', 'Estado'], $header));
        $row = self::HEADER_ROW;
        foreach ($report['documents'] as $document) {
            $writer->addRow(Row::fromValuesWithStyles([
                $document->document_date->toDateTimeImmutable(), $document->document_type->label(), $document->series,
                $document->document_number, $document->authorization_uuid, $document->third_party_tax_id,
                $document->third_party_name, $document->tax_category->label(), (float) $document->taxable_amount,
                (float) $document->exempt_amount, (float) $document->non_taxable_amount, (float) $document->vat_amount,
                (float) $document->other_taxes_amount, (float) $document->total_amount, $document->status->label(),
            ], null, [0 => $date, 8 => $money, 9 => $money, 10 => $money, 11 => $money, 12 => $money, 13 => $money]));
            $row++;
        }
        $sheet->setAutoFilter(new AutoFilter(0, self::HEADER_ROW, 14, max(self::HEADER_ROW, $row)));
        $totals = $report['totals'];
        $writer->addRow(Row::fromValuesWithStyles([null, null, null, null, null, null, null, 'Totales', (float) $totals['taxable_amount'], (float) $totals['exempt_amount'], (float) $totals['non_taxable_amount'], (float) $totals['vat_amount'], (float) $totals['other_taxes_amount'], (float) $totals['total_amount']], $total, [8 => $totalMoney, 9 => $totalMoney, 10 => $totalMoney, 11 => $totalMoney, 12 => $totalMoney, 13 => $totalMoney]));
    }
}
