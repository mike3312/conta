<?php

namespace App\Services\Reports;

use App\Models\Company;
use Carbon\Carbon;
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

class AccountingExcelExporter
{
    private const HEADER_ROW = 7;

    public function generalLedger(Company $company, array $data, CarbonInterface $generatedAt): BinaryFileResponse
    {
        return $this->download('libro-mayor', 'Libro Mayor', $company, $data, $generatedAt, function (Writer $writer, array $data, array $styles): void {
            $sheet = $writer->getCurrentSheet();
            $sheet->setColumnWidth(14, 1, 2, 4);
            $sheet->setColumnWidth(32, 3, 5);
            $sheet->setColumnWidth(16, 6, 7, 8);
            $writer->addRow(Row::fromValues(['Código', 'Cuenta', 'Fecha', 'Póliza', 'Concepto', 'Debe', 'Haber', 'Saldo acumulado'], $styles['header']));
            $hasRows = false;

            foreach ($data['ledgerAccounts'] as $ledger) {
                $hasRows = true;
                $writer->addRow(Row::fromValuesWithStyles([
                    $ledger['account']->code,
                    $ledger['account']->name.' · Naturaleza '.$ledger['account']->nature->label(),
                    null, null, 'Saldo inicial', null, null,
                    (float) $ledger['previous_balance'],
                ], $styles['section'], [7 => $styles['moneySection']]));

                foreach ($ledger['movements'] as $movement) {
                    $writer->addRow(Row::fromValuesWithStyles([
                        $ledger['account']->code,
                        $ledger['account']->name,
                        Carbon::parse($movement['entry_date'])->toDateTimeImmutable(),
                        (int) $movement['number'],
                        $movement['entry_description'].($movement['line_description'] ? ' · '.$movement['line_description'] : ''),
                        (float) $movement['debit'],
                        (float) $movement['credit'],
                        (float) $movement['balance'],
                    ], null, [2 => $styles['date'], 5 => $styles['money'], 6 => $styles['money'], 7 => $styles['money']]));
                }

                $writer->addRow(Row::fromValuesWithStyles([
                    null, null, null, null, 'Totales / saldo final',
                    (float) $ledger['total_debit'], (float) $ledger['total_credit'], (float) $ledger['final_balance'],
                ], $styles['total'], [5 => $styles['moneyTotal'], 6 => $styles['moneyTotal'], 7 => $styles['moneyTotal']]));
            }

            if (! $hasRows) {
                $writer->addRow(Row::fromValues(['No existen datos para los filtros seleccionados.']));
            }
        });
    }

    public function trialBalance(Company $company, array $data, CarbonInterface $generatedAt): BinaryFileResponse
    {
        return $this->download('balance-comprobacion', 'Balance de Comprobación', $company, $data, $generatedAt, function (Writer $writer, array $data, array $styles): void {
            $sheet = $writer->getCurrentSheet();
            $sheet->setColumnWidth(14, 1);
            $sheet->setColumnWidth(34, 2);
            $sheet->setColumnWidth(18, 3, 4, 5, 6, 7, 8);
            $headers = ['Código', 'Cuenta', 'Saldo inicial deudor', 'Saldo inicial acreedor', 'Movimientos debe', 'Movimientos haber', 'Saldo final deudor', 'Saldo final acreedor'];
            $writer->addRow(Row::fromValues($headers, $styles['header']));
            $rowNumber = self::HEADER_ROW;

            foreach ($data['trialBalance'] as $row) {
                $writer->addRow(Row::fromValuesWithStyles([
                    $row['account']->code,
                    str_repeat('   ', max(0, (int) $row['account']->level - 1)).$row['account']->name,
                    (float) $row['previous_debit'], (float) $row['previous_credit'],
                    (float) $row['range_debit'], (float) $row['range_credit'],
                    (float) $row['final_debit'], (float) $row['final_credit'],
                ], null, [2 => $styles['money'], 3 => $styles['money'], 4 => $styles['money'], 5 => $styles['money'], 6 => $styles['money'], 7 => $styles['money']]));
                $rowNumber++;
            }

            if ($rowNumber === self::HEADER_ROW) {
                $writer->addRow(Row::fromValues(['No existen datos para los filtros seleccionados.']));
                $rowNumber++;
            }

            $totals = $data['generalTotals'];
            $writer->addRow(Row::fromValuesWithStyles([
                null, 'Totales generales',
                (float) $totals->previous_debit, (float) $totals->previous_credit,
                (float) $totals->range_debit, (float) $totals->range_credit,
                (float) $totals->final_debit, (float) $totals->final_credit,
            ], $styles['total'], [2 => $styles['moneyTotal'], 3 => $styles['moneyTotal'], 4 => $styles['moneyTotal'], 5 => $styles['moneyTotal'], 6 => $styles['moneyTotal'], 7 => $styles['moneyTotal']]));
            $sheet->setAutoFilter(new AutoFilter(0, self::HEADER_ROW, 7, $rowNumber));
        });
    }

    public function incomeStatement(Company $company, array $data, CarbonInterface $generatedAt): BinaryFileResponse
    {
        return $this->download('estado-resultados', 'Estado de Resultados', $company, $data, $generatedAt, function (Writer $writer, array $data, array $styles): void {
            $writer->getCurrentSheet()->setColumnWidth(16, 1);
            $writer->getCurrentSheet()->setColumnWidth(48, 2);
            $writer->getCurrentSheet()->setColumnWidth(20, 3);
            $writer->addRow(Row::fromValues(['Código', 'Cuenta / concepto', 'Importe'], $styles['header']));
            $rows = 0;
            $this->writeHierarchy($writer, $data['incomeSection'], $styles, $rows);
            $writer->addRow(Row::fromValuesWithStyles([null, 'Total ingresos', (float) $data['totalIncome']], $styles['total'], [2 => $styles['moneyTotal']]));
            $this->writeHierarchy($writer, $data['expenseSection'], $styles, $rows);
            $writer->addRow(Row::fromValuesWithStyles([null, 'Total gastos', (float) $data['totalExpense']], $styles['total'], [2 => $styles['moneyTotal']]));
            $label = $data['resultType'] === 'loss' ? 'Pérdida neta' : 'Utilidad neta';
            $writer->addRow(Row::fromValuesWithStyles([null, $label, (float) $data['result']], $styles['result'], [2 => $styles['moneyResult']]));
            if ($rows === 0) {
                $writer->addRow(Row::fromValues(['No existen datos para los filtros seleccionados.']));
            }
        });
    }

    public function balanceSheet(Company $company, array $data, CarbonInterface $generatedAt): BinaryFileResponse
    {
        return $this->download('balance-general', 'Balance General', $company, $data, $generatedAt, function (Writer $writer, array $data, array $styles): void {
            $writer->getCurrentSheet()->setColumnWidth(16, 1);
            $writer->getCurrentSheet()->setColumnWidth(48, 2);
            $writer->getCurrentSheet()->setColumnWidth(20, 3);
            $writer->addRow(Row::fromValues(['Código', 'Cuenta / concepto', 'Importe'], $styles['header']));
            $rows = 0;
            foreach ([['Activos', 'assetSection', 'totalAssets'], ['Pasivos', 'liabilitySection', 'totalLiabilities'], ['Patrimonio', 'equitySection', 'baseEquity']] as [$title, $section, $total]) {
                $writer->addRow(Row::fromValues([null, $title], $styles['section']));
                $this->writeHierarchy($writer, $data[$section], $styles, $rows);
                $writer->addRow(Row::fromValuesWithStyles([null, 'Total '.$title, (float) $data[$total]], $styles['total'], [2 => $styles['moneyTotal']]));
            }
            $writer->addRow(Row::fromValuesWithStyles([null, 'Resultado pendiente de aplicar', (float) $data['pendingResult']], $styles['total'], [2 => $styles['moneyTotal']]));
            $writer->addRow(Row::fromValuesWithStyles([null, 'Total patrimonio', (float) $data['totalEquity']], $styles['total'], [2 => $styles['moneyTotal']]));
            $writer->addRow(Row::fromValuesWithStyles([null, 'Total pasivos + patrimonio', (float) $data['liabilitiesAndEquity']], $styles['result'], [2 => $styles['moneyResult']]));
            $writer->addRow(Row::fromValuesWithStyles([null, 'Diferencia'.($data['isBalanced'] ? ' (cuadrado)' : ' (advertencia: no cuadra)'), (float) $data['difference']], $styles['total'], [2 => $styles['moneyTotal']]));
            if ($rows === 0 && (float) $data['pendingResult'] === 0.0) {
                $writer->addRow(Row::fromValues(['No existen datos para los filtros seleccionados.']));
            }
        });
    }

    private function writeHierarchy(Writer $writer, array $nodes, array $styles, int &$rows, int $level = 0): void
    {
        foreach ($nodes as $node) {
            $rows++;
            $writer->addRow(Row::fromValuesWithStyles([
                $node['account']->code,
                str_repeat('   ', $level).$node['account']->name,
                (float) ($node['balance'] ?? $node['net']),
            ], count($node['children']) ? $styles['section'] : null, [2 => count($node['children']) ? $styles['moneySection'] : $styles['money']]));
            $this->writeHierarchy($writer, $node['children'], $styles, $rows, $level + 1);
            if (count($node['children'])) {
                $writer->addRow(Row::fromValuesWithStyles([null, 'Subtotal '.$node['account']->name, (float) $node['subtotal']], $styles['total'], [2 => $styles['moneyTotal']]));
            }
        }
    }

    private function download(string $slug, string $title, Company $company, array $data, CarbonInterface $generatedAt, callable $writerCallback): BinaryFileResponse
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), $slug.'-');
        abort_if($temporaryPath === false, 500, 'No fue posible preparar la exportación de Excel.');
        $options = new Options;
        $options->DEFAULT_ROW_STYLE->setFontName('Arial')->setFontSize(10);

        try {
            $writer = new Writer($options);
            $writer->openToFile($temporaryPath);
            try {
                $sheet = $writer->getCurrentSheet();
                $sheet->setName(mb_substr($title, 0, 31));
                $sheet->setSheetView((new SheetView)->setFreezeRow(self::HEADER_ROW + 1));
                $sheet->setPrintTitleRows(self::HEADER_ROW.':'.self::HEADER_ROW);
                $styles = $this->styles();
                $writer->addRow(Row::fromValuesWithStyles(['Empresa', $company->name], null, [0 => $styles['label']]));
                $writer->addRow(Row::fromValuesWithStyles(['NIT', $company->tax_id ?: 'No registrado'], null, [0 => $styles['label']]));
                $writer->addRow(Row::fromValues([$title], $styles['title']));
                $writer->addRow(Row::fromValuesWithStyles(['Período / rango', $this->periodDescription($data['filters'])], null, [0 => $styles['label']]));
                $writer->addRow(Row::fromValuesWithStyles(['Generado', $generatedAt->format('d/m/Y H:i:s')], null, [0 => $styles['label']]));
                $writer->addRow(Row::fromValues([]));
                $writerCallback($writer, $data, $styles);
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

    private function styles(): array
    {
        return [
            'label' => (new Style)->setFontBold()->setFontColor(Color::DARK_BLUE),
            'title' => (new Style)->setFontBold()->setFontSize(16)->setFontColor(Color::DARK_BLUE),
            'header' => (new Style)->setFontBold()->setFontColor(Color::WHITE)->setBackgroundColor(Color::DARK_BLUE)->setCellAlignment(CellAlignment::CENTER),
            'date' => (new Style)->setFormat('dd/mm/yyyy'),
            'money' => (new Style)->setFormat('Q #,##0.00')->setCellAlignment(CellAlignment::RIGHT),
            'section' => (new Style)->setFontBold()->setBackgroundColor('EAF2F8'),
            'moneySection' => (new Style)->setFontBold()->setBackgroundColor('EAF2F8')->setFormat('Q #,##0.00')->setCellAlignment(CellAlignment::RIGHT),
            'total' => (new Style)->setFontBold()->setBackgroundColor('D9EAF7'),
            'moneyTotal' => (new Style)->setFontBold()->setBackgroundColor('D9EAF7')->setFormat('Q #,##0.00')->setCellAlignment(CellAlignment::RIGHT),
            'result' => (new Style)->setFontBold()->setFontSize(12)->setFontColor(Color::WHITE)->setBackgroundColor(Color::DARK_BLUE),
            'moneyResult' => (new Style)->setFontBold()->setFontSize(12)->setFontColor(Color::WHITE)->setBackgroundColor(Color::DARK_BLUE)->setFormat('Q #,##0.00')->setCellAlignment(CellAlignment::RIGHT),
        ];
    }

    private function periodDescription(array $filters): string
    {
        if (isset($filters['cutoff_date'])) {
            return 'Al '.$filters['cutoff_date'];
        }
        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            return $filters['date_from'].' al '.$filters['date_to'];
        }

        return ! empty($filters['date_from']) ? 'Desde '.$filters['date_from'] : (! empty($filters['date_to']) ? 'Hasta '.$filters['date_to'] : 'Todos los períodos');
    }
}
