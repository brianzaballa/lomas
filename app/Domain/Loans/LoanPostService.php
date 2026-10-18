<?php

namespace App\Domain\Loans;

use App\Models\Loan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Posts a saved loan (marks as active and generates amortization schedule).
 */
class LoanPostService
{
    public function __construct(
        private readonly AmortizationScheduleGenerator $generator,
    ) {}

    public function post(Loan $loan): Loan
    {
        if ($loan->status !== Loan::STATUS_DRAFT) {
            throw new LogicException('Only draft loans can be posted.');
        }

        return DB::transaction(function () use ($loan): Loan {
            $releaseDate = Carbon::now();

            $loan->status = Loan::STATUS_ACTIVE;
            $loan->release_date = $releaseDate;
            $loan->save();

            $this->writeSchedule($loan, $releaseDate);

            return $loan->refresh();
        });
    }

    private function writeSchedule(Loan $loan, Carbon $releaseDate): void
    {
        $now = Carbon::now()->toDateTimeString();

        $rows = [];
        foreach ($this->generator->generate($loan, $releaseDate) as $installment) {
            $rows[] = [
                'id' => (string) Str::uuid7(),
                'node_id' => $loan->node_id,
                'loan_id' => $loan->getKey(),
                'installment_number' => $installment['installment_number'],
                'due_date' => $installment['due_date'],
                'principal_due' => $installment['principal_due'],
                'interest_due' => $installment['interest_due'],
                'total_due' => $installment['total_due'],
                'paid_amount' => $installment['paid_amount'],
                'remaining_amount' => $installment['remaining_amount'],
                'status' => $installment['status'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('loan_amortizations')->insert($chunk);
        }
    }
}
