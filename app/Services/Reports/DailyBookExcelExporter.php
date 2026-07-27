<?php

namespace App\Services\Reports;

use App\Models\Company;
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

class DailyBookExcelExporter
{
    private const HEADER_ROW = 8;

    public function download(Company $company, array $report, CarbonInterface $generatedAt): BinaryFileResponse
    {
        $filename = ReportFilename::make('libro-diario', $company, $generatedAt, 'xlsx');
        $temporaryPath = tempnam(sys_get_temp_dir(), 'libro-diario-');

        abort_if($temporaryPath === false, 500, 'No fue posible preparar la exportación de Excel.');

        $options = new Options;
        $options->DEFAULT_ROW_STYLE->setFontName('Arial')->setFontSize(10);

        try {
            $writer = new Writer($options);
            $writer->openToFile($temporaryPath);

            try {
                $this->writeWorkbook($writer, $company, $report, $generatedAt);
            } finally {
                $writer->close();
            }
        } catch (Throwable $exception) {
            @unlink($temporaryPath);

            throw $exception;
        }

        return response()
            ->download($temporaryPath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    private function writeWorkbook(
        Writer $writer,
        Company $company,
        array $report,
        CarbonInterface $generatedAt
    ): void {
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Libro Diario');
        $sheet->setSheetView((new SheetView)->setFreezeRow(self::HEADER_ROW + 1));
        $sheet->setPrintTitleRows(self::HEADER_ROW.':'.self::HEADER_ROW);
        $sheet->setColumnWidth(14, 1, 2, 4);
        $sheet->setColumnWidth(34, 3, 5);
        $sheet->setColumnWidth(16, 6, 7);

        $labelStyle = (new Style)->setFontBold()->setFontColor(Color::DARK_BLUE);
        $titleStyle = (new Style)->setFontBold()->setFontSize(16)->setFontColor(Color::DARK_BLUE);
        $headerStyle = (new Style)
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor(Color::DARK_BLUE)
            ->setCellAlignment(CellAlignment::CENTER);
        $dateStyle = (new Style)->setFormat('dd/mm/yyyy');
        $moneyStyle = (new Style)->setFormat('#,##0.00')->setCellAlignment(CellAlignment::RIGHT);
        $totalStyle = (new Style)->setFontBold()->setBackgroundColor('D9EAF7');
        $totalMoneyStyle = (new Style)
            ->setFontBold()
            ->setBackgroundColor('D9EAF7')
            ->setFormat('#,##0.00')
            ->setCellAlignment(CellAlignment::RIGHT);

        $writer->addRow(Row::fromValuesWithStyles(
            ['Empresa', $company->name],
            null,
            [0 => $labelStyle]
        ));
        $writer->addRow(Row::fromValuesWithStyles(
            ['NIT', $company->tax_id ?: 'No registrado'],
            null,
            [0 => $labelStyle]
        ));
        $writer->addRow(Row::fromValues(['Libro Diario'], $titleStyle));
        $writer->addRow(Row::fromValuesWithStyles(
            ['Período / rango', $report['periodDescription']],
            null,
            [0 => $labelStyle]
        ));
        $writer->addRow(Row::fromValuesWithStyles(
            ['Generado', $generatedAt->format('d/m/Y H:i:s')],
            null,
            [0 => $labelStyle]
        ));
        $writer->addRow(Row::fromValuesWithStyles(
            ['Moneda', $company->currency ?: 'GTQ'],
            null,
            [0 => $labelStyle]
        ));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([
            'Número de póliza',
            'Fecha',
            'Concepto',
            'Código de cuenta',
            'Cuenta',
            'Debe',
            'Haber',
        ], $headerStyle));

        $dataRow = self::HEADER_ROW;

        foreach ($report['entries'] as $entry) {
            foreach ($entry->lines as $line) {
                $writer->addRow(Row::fromValuesWithStyles([
                    (int) $entry->number,
                    $entry->entry_date->toDateTimeImmutable(),
                    $entry->description,
                    $line->account->code,
                    $line->account->name,
                    (float) $line->debit,
                    (float) $line->credit,
                ], null, [
                    1 => $dateStyle,
                    5 => $moneyStyle,
                    6 => $moneyStyle,
                ]));

                $dataRow++;
            }
        }

        $sheet->setAutoFilter(new AutoFilter(0, self::HEADER_ROW, 6, max(self::HEADER_ROW, $dataRow)));

        $writer->addRow(Row::fromValuesWithStyles([
            null,
            null,
            null,
            null,
            'Totales generales',
            (float) $report['totalDebit'],
            (float) $report['totalCredit'],
        ], $totalStyle, [
            5 => $totalMoneyStyle,
            6 => $totalMoneyStyle,
        ]));
    }
}
