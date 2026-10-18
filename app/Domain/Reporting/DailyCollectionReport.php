<?php

namespace App\Domain\Reporting;

use App\Domain\Settings\SystemSettings;
use App\Models\Payment;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * A compact, one-day collection report grouped by area.
 *
 * It answers the operational question "how much was collected in each area
 * today?" in a single page that can be printed and handed out. Every figure is
 * derived from posted payments; nothing is stored, so the printed report always
 * reconciles with the transactions it summarises. See AGENTS.md sections 34,
 * 35 and 38.
 */
class DailyCollectionReport
{
    public function __construct(private readonly SystemSettings $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function build(CarbonImmutable $date): array
    {
        $settings = $this->settings->get();

        $payments = Payment::query()
            ->where('status', Payment::STATUS_POSTED)
            ->whereDate('payment_date', $date->toDateString())
            ->with(['loan.borrower', 'loan.area', 'loan.node.area'])
            ->orderBy('payment_number')
            ->get();

        $total = $payments->reduce(
            fn (Money $carry, Payment $payment): Money => $carry->add(Money::of($payment->amount)),
            Money::zero(),
        );

        return [
            'date' => $date->toDateString(),
            'weekday' => $date->format('l'),
            'label' => $date->format('F j, Y'),
            'previous_date' => $date->subDay()->toDateString(),
            'next_date' => $date->addDay()->toDateString(),
            'is_today' => $date->isToday(),
            'areas' => $this->byArea($payments),
            'methods' => $this->byMethod($payments),
            'total' => $this->settings->formatAmount($total),
            'total_count' => $payments->count(),
            'business' => [
                'name' => $settings->business_name,
                'address' => $settings->business_address,
                'contact_phone' => $settings->contact_phone,
                'contact_email' => $settings->contact_email,
            ],
            'currency' => $settings->currency,
            'generated_at' => now()->format('M j, Y g:i A'),
        ];
    }

    /**
     * One group per area holding that area's payments, ordered by area name.
     *
     * @param  Collection<int, Payment>  $payments
     * @return list<array<string, mixed>>
     */
    private function byArea($payments): array
    {
        $grouped = $payments->groupBy(
            fn (Payment $payment): string => $this->areaName($payment),
        );

        $rows = [];

        foreach ($grouped as $area => $areaPayments) {
            $amount = $areaPayments->reduce(
                fn (Money $carry, Payment $payment): Money => $carry->add(Money::of($payment->amount)),
                Money::zero(),
            );

            $rows[] = [
                'area' => (string) $area,
                'count' => $areaPayments->count(),
                'total' => $this->settings->formatAmount($amount),
                'payments' => $areaPayments
                    ->sortBy('payment_number')
                    ->map(fn (Payment $payment): array => [
                        'payment_number' => (string) $payment->payment_number,
                        'loan_number' => (string) $payment->loan->loan_number,
                        'borrower_name' => $payment->loan->borrower->fullName(),
                        'borrower_number' => (string) $payment->loan->borrower->borrower_number,
                        'payment_method' => $payment->payment_method,
                        'payment_method_label' => ucfirst(str_replace('_', ' ', (string) $payment->payment_method)),
                        'reference_number' => $payment->reference_number,
                        'amount' => $this->settings->formatAmount(Money::of($payment->amount)),
                    ])
                    ->values()
                    ->all(),
            ];
        }

        usort($rows, function (array $a, array $b): int {
            return strcmp($a['area'], $b['area']);
        });

        return $rows;
    }

    /**
     * A compact cashier breakdown by payment method.
     *
     * @param  Collection<int, Payment>  $payments
     * @return list<array<string, mixed>>
     */
    private function byMethod($payments): array
    {
        $grouped = $payments->groupBy('payment_method');

        $rows = [];

        foreach ($grouped as $method => $methodPayments) {
            $amount = $methodPayments->reduce(
                fn (Money $carry, Payment $payment): Money => $carry->add(Money::of($payment->amount)),
                Money::zero(),
            );

            $rows[] = [
                'method' => (string) $method,
                'label' => ucfirst(str_replace('_', ' ', (string) $method)),
                'count' => $methodPayments->count(),
                'total' => $this->settings->formatAmount($amount),
            ];
        }

        usort($rows, function (array $a, array $b): int {
            return strcmp($a['method'], $b['method']);
        });

        return $rows;
    }

    private function areaName(Payment $payment): string
    {
        return $payment->loan->area->name
            ?? $payment->loan->node->area->name
            ?? 'Unassigned';
    }
}
