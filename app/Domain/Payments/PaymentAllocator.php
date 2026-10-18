<?php

namespace App\Domain\Payments;

use App\Models\PaymentAllocation;
use App\Support\Money;

/**
 * Spreads a payment across the installments it settles.
 *
 * The waterfall is explicit: oldest unpaid installment first, and within an
 * installment the interest owed before the principal. Every installment of the
 * current product is principal, so a payment settles principal only. Any amount
 * beyond the total owed is returned as unapplied and is never pushed into a
 * future installment. See AGENTS.md sections 30, 31, 32 and 63.1.5.
 *
 * This is a pure calculation: it writes nothing and can be unit tested on its
 * own.
 */
class PaymentAllocator
{
    /**
     * @param  list<array{loan_amortization_id: string, interest_remaining: string, principal_remaining: string}>  $installments  Oldest installment first.
     * @return array{
     *     allocations: list<array{loan_amortization_id: string, component: string, amount: string}>,
     *     allocated: string,
     *     unapplied: string
     * }
     */
    public function allocate(Money $amount, array $installments): array
    {
        $available = $amount;
        $allocations = [];

        foreach ($installments as $installment) {
            $components = [
                PaymentAllocation::COMPONENT_INTEREST => $installment['interest_remaining'],
                PaymentAllocation::COMPONENT_PRINCIPAL => $installment['principal_remaining'],
            ];

            foreach ($components as $component => $remaining) {
                if ($available->isZero()) {
                    break 2;
                }

                $remainingMoney = Money::of($remaining);

                if (! $remainingMoney->isPositive()) {
                    continue;
                }

                $take = $available->lessThan($remainingMoney) ? $available : $remainingMoney;

                $allocations[] = [
                    'loan_amortization_id' => $installment['loan_amortization_id'],
                    'component' => $component,
                    'amount' => $take->toDecimalString(),
                ];

                $available = $available->subtract($take);
            }
        }

        return [
            'allocations' => $allocations,
            'allocated' => $amount->subtract($available)->toDecimalString(),
            'unapplied' => $available->toDecimalString(),
        ];
    }
}
