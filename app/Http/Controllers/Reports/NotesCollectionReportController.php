<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Access\Permission;
use App\Domain\Reporting\NcrPeriod;
use App\Domain\Reporting\NcrPeriodResolver;
use App\Domain\Reporting\NcrPostingService;
use App\Domain\Reporting\NotesCollectionReport;
use App\Domain\Reporting\ReportPdf;
use App\Domain\Settings\SystemSettings;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * The Notes Collection Report, on screen, as a document and as a posting.
 *
 * Totals are always recalculated from posted payments and the frozen schedule,
 * never stored, so the printed report and the transactions it summarises cannot
 * drift apart. See AGENTS.md sections 34 to 36 and 38.
 */
class NotesCollectionReportController extends Controller
{
    /**
     * The longest ad-hoc range the report will render in one request.
     */
    private const MAX_RANGE_DAYS = 31;

    public function __construct(
        private readonly NcrPeriodResolver $periods,
        private readonly SystemSettings $settings,
        private readonly NcrPostingService $postings,
    ) {}

    public function index(Request $request, NotesCollectionReport $report): InertiaResponse
    {
        $this->authorize(Permission::REPORTS_VIEW);

        $period = $this->period($request);

        return Inertia::render('reports/NotesCollection', [
            'report' => $report->build($period),
            'week' => $period->anchor(),
        ]);
    }

    public function export(Request $request, NotesCollectionReport $report, ReportPdf $pdf): Response
    {
        $this->authorize(Permission::REPORTS_EXPORT);

        $period = $this->period($request);

        $data = $report->build($period);

        if ($request->string('format')->lower()->toString() === 'csv') {
            return $this->csv($data);
        }

        $filename = sprintf('notes-collection-%s.pdf', $data['week_start']);

        return $request->boolean('download')
            ? $pdf->download('reports.notes-collection', $data, $filename, 'a4', 'landscape')
            : $pdf->inline('reports.notes-collection', $data, $filename, 'a4', 'landscape');
    }

    /**
     * Post the period, the sign-off that locks its payments.
     */
    public function post(Request $request): RedirectResponse
    {
        $this->authorize(Permission::REPORTS_POST);

        $this->postings->post($this->period($request));

        $this->success('Notes Collection Report period posted.');

        return back();
    }

    /**
     * Unpost the period, restoring its payments for correction.
     */
    public function unpost(Request $request): RedirectResponse
    {
        $this->authorize(Permission::REPORTS_POST);

        $this->postings->unpost($this->period($request));

        $this->success('Notes Collection Report period unposted.');

        return back();
    }

    /**
     * Resolve the reporting period.
     *
     * A caller may pass an anchor date, `period` or the legacy `week`, and the
     * configured period mode decides the boundaries. A caller may instead pass
     * an explicit `from` and `to` range, which is resolved as a one-off period;
     * that keeps an arbitrary range printable without changing the mode.
     */
    private function period(Request $request): NcrPeriod
    {
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        if ($from !== '' && $to !== '' && strtotime($from) !== false && strtotime($to) !== false) {
            $start = CarbonImmutable::parse($from)->startOfDay();
            $end = CarbonImmutable::parse($to)->startOfDay();

            if ($end->lessThan($start)) {
                [$start, $end] = [$end, $start];
            }

            if ($end->greaterThan($start->addDays(self::MAX_RANGE_DAYS))) {
                $end = $start->addDays(self::MAX_RANGE_DAYS);
            }

            return new NcrPeriod($start, $end, 'custom');
        }

        return $this->periods->resolve($this->settings->ncrPeriodMode(), $this->anchor($request));
    }

    private function anchor(Request $request): CarbonImmutable
    {
        foreach (['period', 'week'] as $key) {
            $value = $request->string($key)->toString();

            if ($value !== '' && strtotime($value) !== false) {
                return CarbonImmutable::parse($value);
            }
        }

        return CarbonImmutable::now();
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

        $hasCollectibles = $data['period']['is_current'];

        $header = ['Area', 'Member', 'Loan #'];

        foreach ($data['day_headers'] as $day) {
            $header[] = $day['weekday'].' '.$day['date'];
        }

        $header[] = 'Total collected';

        if ($hasCollectibles) {
            $header[] = 'Collectibles';
        }

        $header[] = 'Overdue';

        fputcsv($stream, $header);

        foreach ($data['areas'] as $area) {
            foreach ($area['members'] as $member) {
                $row = [$area['area'], $member['borrower_name'], $member['loan_number']];

                foreach ($data['day_headers'] as $day) {
                    $row[] = $this->csvAmount($member['days'][$day['date']] ?? '');
                }

                $row[] = $this->csvAmount($member['total_collected']);

                if ($hasCollectibles) {
                    $row[] = $this->csvAmount((string) $member['collectibles']);
                }

                $row[] = $this->csvAmount($member['overdue']);

                fputcsv($stream, $row);
            }

            $subtotal = [
                $area['area'].' subtotal',
                $area['count'].' members',
                '',
                ...array_fill(0, count($data['day_headers']), ''),
                $this->csvAmount($area['subtotal_collected']),
            ];

            if ($hasCollectibles) {
                $subtotal[] = $this->csvAmount((string) $area['subtotal_collectibles']);
            }

            $subtotal[] = $this->csvAmount($area['subtotal_overdue']);

            fputcsv($stream, $subtotal);
        }

        $grand = [
            'Grand total',
            $data['grand']['count'].' members',
            '',
            ...array_fill(0, count($data['day_headers']), ''),
            $this->csvAmount($data['grand']['total_collected']),
        ];

        if ($hasCollectibles) {
            $grand[] = $this->csvAmount((string) $data['grand']['total_collectibles']);
        }

        $grand[] = $this->csvAmount($data['grand']['total_overdue']);

        fputcsv($stream, $grand);

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="notes-collection-%s.csv"', $data['week_start']),
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
