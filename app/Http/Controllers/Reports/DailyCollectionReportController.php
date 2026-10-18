<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Access\Permission;
use App\Domain\Reporting\DailyCollectionReport;
use App\Domain\Reporting\ReportPdf;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * The compact Daily Collection Report, on screen and as a document.
 *
 * Totals are recalculated from posted payments, never stored, so the printed
 * report and the transactions it summarises cannot drift apart. See AGENTS.md
 * sections 34 to 38.
 */
class DailyCollectionReportController extends Controller
{
    public function index(Request $request, DailyCollectionReport $report): InertiaResponse
    {
        $this->authorize(Permission::REPORTS_VIEW);

        $date = $this->date($request);

        return Inertia::render('reports/DailyCollection', [
            'report' => $report->build($date),
            'date' => $date->toDateString(),
        ]);
    }

    public function export(Request $request, DailyCollectionReport $report, ReportPdf $pdf): Response
    {
        $this->authorize(Permission::REPORTS_EXPORT);

        $date = $this->date($request);
        $data = $report->build($date);

        if ($request->string('format')->lower()->toString() === 'csv') {
            return $this->csv($data);
        }

        $filename = sprintf('daily-collection-%s.pdf', $data['date']);

        return $request->boolean('download')
            ? $pdf->download('reports.daily-collection', $data, $filename)
            : $pdf->inline('reports.daily-collection', $data, $filename);
    }

    private function date(Request $request): CarbonImmutable
    {
        $value = $request->string('date')->toString();

        return $value !== '' && strtotime($value) !== false
            ? CarbonImmutable::parse($value)
            : CarbonImmutable::now();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function csv(array $data): Response
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            abort(500, 'Unable to build the export.');
        }

        fputcsv($stream, ['Daily Collection Report', $data['date']]);
        fputcsv($stream, []);

        fputcsv($stream, ['Area', 'Payment #', 'Loan #', 'Client', 'Method', 'Reference', 'Amount']);

        foreach ($data['areas'] as $area) {
            foreach ($area['payments'] as $payment) {
                fputcsv($stream, [
                    $area['area'],
                    $payment['payment_number'],
                    $payment['loan_number'],
                    $payment['borrower_name'],
                    $payment['payment_method'],
                    $payment['reference_number'],
                    $this->csvAmount($payment['amount']),
                ]);
            }

            fputcsv($stream, [$area['area'].' subtotal', '', '', '', '', '', $this->csvAmount($area['total'])]);
        }

        fputcsv($stream, ['Grand total', '', '', '', '', '', $this->csvAmount($data['total'])]);
        fputcsv($stream, []);
        fputcsv($stream, ['Payment method', 'Payments', 'Amount collected']);

        foreach ($data['methods'] as $method) {
            fputcsv($stream, [$method['label'], $method['count'], $this->csvAmount($method['total'])]);
        }

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="daily-collection-%s.csv"', $data['date']),
        ]);
    }

    /**
     * Drop the thousands separators so spreadsheet software reads a number.
     */
    private function csvAmount(string $amount): string
    {
        return str_replace(',', '', $amount);
    }
}
