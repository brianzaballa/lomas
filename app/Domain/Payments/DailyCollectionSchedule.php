<?php

namespace App\Domain\Payments;

use App\Models\Loan;
use App\Models\LoanAmortization;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * The loans that should be collected on a given business date.
 *
 * A loan appears when it has an installment due on or before the date that is
 * not yet fully paid, so arrears stay visible on later collection days instead
 * of being forgotten. This is a read model built from the frozen schedule; it
 * holds no financial rule of its own. See AGENTS.md sections 28 and 63.1.10.
 */
class DailyCollectionSchedule
{
    /**
     * @return list<array{
     *     loan_id: string,
     *     loan_number: string,
     *     borrower_number: string,
     *     borrower_name: string,
     *     area: string,
     *     oldest_due_date: string,
     *     scheduled_amount: string,
     *     paid_amount: string,
     *     remaining_amount: string
     * }>
     */
    public function forDate(CarbonInterface $date, ?string $area = null, ?string $name = null): array
    {
        $installments = LoanAmortization::query()
            ->whereDate('due_date', '<=', $date->toDateString())
            ->where('remaining_amount', '>', 0)
            ->whereIn('loan_id', Loan::query()
                ->whereIn('status', [
                    Loan::STATUS_ACTIVE,
                ])
                ->when($area !== null && $area !== '', fn (Builder $query) => $query
                    ->where(fn (Builder $query) => $query
                        ->whereHas('area', fn (Builder $relation) => $relation->where('id', $area))
                        ->orWhereHas('node.area', fn (Builder $relation) => $relation->where('id', $area))))
                ->when($name !== null && $name !== '', fn (Builder $query) => $query
                    ->whereHas('borrower', fn (Builder $borrower) => $borrower->search($name)))
                ->select('id'))
            ->with(['loan.borrower', 'loan.area', 'loan.node.area'])
            ->orderBy('due_date')
            ->orderBy('installment_number')
            ->get();

        $rows = [];

        foreach ($installments->groupBy('loan_id') as $group) {
            $loan = $group->first()->loan;

            $scheduled = Money::zero();
            $paid = Money::zero();
            $remaining = Money::zero();

            foreach ($group as $installment) {
                $scheduled = $scheduled->add(Money::of($installment->total_due));
                $paid = $paid->add(Money::of($installment->paid_amount));
                $remaining = $remaining->add(Money::of($installment->remaining_amount));
            }

            $rows[] = [
                'loan_id' => (string) $loan->getKey(),
                'loan_number' => (string) $loan->loan_number,
                'borrower_number' => (string) $loan->borrower->borrower_number,
                'borrower_name' => $loan->borrower->fullName(),
                'area' => $loan->area->name ?? $loan->node->area->name ?? 'Unassigned',
                'oldest_due_date' => $group->first()->due_date->toDateString(),
                'scheduled_amount' => $scheduled->toDecimalString(),
                'paid_amount' => $paid->toDecimalString(),
                'remaining_amount' => $remaining->toDecimalString(),
            ];
        }

        return $rows;
    }
}
