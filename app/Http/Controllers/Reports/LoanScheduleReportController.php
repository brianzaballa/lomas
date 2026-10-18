<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Access\Permission;
use App\Domain\Reporting\LoanScheduleReport;
use App\Domain\Reporting\ReportPdf;
use App\Http\Controllers\Controller;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The printable amortization schedule of a single loan.
 *
 * The schedule is read straight from the frozen rows written at release, so the
 * printed document always matches the loan that was actually issued. See
 * AGENTS.md sections 36, 57 and 63.1.9.
 */
class LoanScheduleReportController extends Controller
{
    public function __invoke(
        Request $request,
        Loan $loan,
        LoanScheduleReport $report,
        ReportPdf $pdf,
    ): Response {
        $this->authorize(Permission::LOANS_VIEW);

        $data = $report->data($loan);
        $filename = sprintf('amortization-schedule-%s.pdf', $loan->loan_number);

        return $request->boolean('download')
            ? $pdf->download('reports.loan-schedule', $data, $filename)
            : $pdf->inline('reports.loan-schedule', $data, $filename);
    }
}
