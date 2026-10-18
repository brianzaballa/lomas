<?php

namespace App\Domain\Loans;

use App\Enums\DeductionCalculationMethod;
use App\Enums\InterestMethod;
use App\Models\LoanDeductionType;
use App\Models\LoanProduct;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Calculates the values for a loan being encoded.
 *
 * The same calculation serves the on-screen preview and the persisted draft,
 * so the two can never disagree. Laravel recalculates before persistence, which
 * keeps the UI from ever becoming authoritative.
 *
 * Interest is a deduction the borrower pays, but it is not a deduction type:
 * the rate belongs to the loan product. Every other deduction comes from the
 * types the product charges, which an encoder may switch off for one loan and
 * add to, see AGENTS.md sections 63.1.2 and 63.1.3.
 */
class LoanCalculator
{
    /**
     * The code used for the interest row in the deduction breakdown.
     *
     * There is deliberately no Interest deduction type, so the breakdown needs
     * a stable label that is not a configured code.
     */
    public const string INTEREST_CODE = 'INTEREST';

    /**
     * @return array{
     *     gross_loan_amount: string,
     *     interest_amount: string,
     *     other_deductions_amount: string,
     *     deductions_amount: string,
     *     net_proceeds_amount: string,
     *     total_amount_payable: string,
     *     daily_amortization: string,
     *     collection_days: int,
     *     deductions: list<array{
     *         deduction_type_id: string|null,
     *         code: string|null,
     *         name: string,
     *         calculation_method: string,
     *         rate: string|null,
     *         fixed_amount: string|null,
     *         base_amount: string,
     *         amount: string,
     *         override_amount: string|null,
     *         is_interest: bool,
     *         is_applied: bool,
     *         is_custom: bool,
     *         is_toggleable: bool,
     *         sort_order: int
     *     }>
     * }
     */
    public function preview(
        LoanProduct $product,
        string $grossLoanAmount,
        ?string $interestOverrideAmount = null,
        ?DeductionSelection $selection = null,
    ): array {
        $gross = BigDecimal::of($grossLoanAmount)->toScale(2, RoundingMode::HalfUp);
        $selection ??= DeductionSelection::none();

        $deductions = [];
        $sortOrder = 0;

        $interest = $this->interest($product, $gross, $interestOverrideAmount);

        $deductions[] = $this->row(
            deductionTypeId: null,
            code: self::INTEREST_CODE,
            name: 'Interest',
            method: InterestMethod::PercentageOfLoanAmount->value,
            rate: $this->overrideAmount($interestOverrideAmount) === null
                ? $product->interest_rate
                : null,
            fixedAmount: null,
            baseAmount: $gross,
            amount: $interest,
            overrideAmount: $this->overrideAmount($interestOverrideAmount),
            isInterest: true,
            isApplied: true,
            isCustom: false,
            isToggleable: false,
            sortOrder: $sortOrder++,
        );

        $otherTotal = BigDecimal::zero()->toScale(2);

        foreach ($product->activeDeductionTypes() as $type) {
            $amount = $this->deductionAmount($type, $gross);
            $isApplied = ! $selection->excludes($type->code);

            $deductions[] = $this->row(
                deductionTypeId: $type->getKey(),
                code: $type->code,
                name: $type->name,
                method: $type->calculation_method->value,
                rate: $type->rate,
                fixedAmount: $type->fixed_amount,
                baseAmount: $gross,
                amount: $amount,
                overrideAmount: null,
                isInterest: false,
                isApplied: $isApplied,
                isCustom: false,
                isToggleable: true,
                sortOrder: $sortOrder++,
            );

            if ($isApplied) {
                $otherTotal = $otherTotal->plus($amount);
            }
        }

        foreach ($selection->customDeductions() as $custom) {
            $amount = BigDecimal::of($custom['amount'])->toScale(2, RoundingMode::HalfUp);

            $deductions[] = $this->row(
                deductionTypeId: null,
                code: null,
                name: $custom['name'],
                method: DeductionCalculationMethod::FixedAmount->value,
                rate: null,
                fixedAmount: $amount->__toString(),
                baseAmount: $gross,
                amount: $amount,
                overrideAmount: null,
                isInterest: false,
                isApplied: true,
                isCustom: true,
                isToggleable: false,
                sortOrder: $sortOrder++,
            );

            $otherTotal = $otherTotal->plus($amount);
        }

        $deductionsTotal = $interest->plus($otherTotal);

        $collectionDays = $this->collectionDays($product);
        $daily = $gross->dividedBy((string) $collectionDays, 2, RoundingMode::HalfUp);

        return [
            'gross_loan_amount' => $gross->__toString(),
            'interest_amount' => $interest->__toString(),
            'other_deductions_amount' => $otherTotal->__toString(),
            'deductions_amount' => $deductionsTotal->__toString(),
            'net_proceeds_amount' => $gross->minus($deductionsTotal)->__toString(),
            // Interest is withheld at release, so the schedule collects the
            // gross loan amount. See AGENTS.md section 63.1.2.
            'total_amount_payable' => $gross->__toString(),
            'daily_amortization' => $daily->__toString(),
            'collection_days' => $collectionDays,
            'deductions' => $deductions,
        ];
    }

    public function collectionDays(LoanProduct $product): int
    {
        return $product->term;
    }

    /**
     * Interest, either the product rate applied to the gross amount or an
     * amount the encoder entered by hand.
     */
    private function interest(LoanProduct $product, BigDecimal $gross, ?string $interestOverrideAmount): BigDecimal
    {
        $override = $this->overrideAmount($interestOverrideAmount);

        if ($override !== null) {
            return $override;
        }

        return $gross->multipliedBy($product->interest_rate)->dividedBy('100', 2, RoundingMode::HalfUp);
    }

    private function deductionAmount(LoanDeductionType $type, BigDecimal $gross): BigDecimal
    {
        return match ($type->calculation_method) {
            DeductionCalculationMethod::PercentageOfLoanAmount => $gross
                ->multipliedBy($type->rate ?? '0')
                ->dividedBy('100', 2, RoundingMode::HalfUp),
            DeductionCalculationMethod::FixedAmount => BigDecimal::of($type->fixed_amount ?? '0')
                ->toScale(2, RoundingMode::HalfUp),
        };
    }

    /**
     * A blank field means "use the product rate", which is what an untouched
     * override box should do.
     */
    private function overrideAmount(?string $interestOverrideAmount): ?BigDecimal
    {
        if ($interestOverrideAmount === null || trim($interestOverrideAmount) === '') {
            return null;
        }

        return BigDecimal::of(trim($interestOverrideAmount))->toScale(2, RoundingMode::HalfUp);
    }

    /**
     * @return array{
     *     deduction_type_id: string|null,
     *     code: string|null,
     *     name: string,
     *     calculation_method: string,
     *     rate: string|null,
     *     fixed_amount: string|null,
     *     base_amount: string,
     *     amount: string,
     *     override_amount: string|null,
     *     is_interest: bool,
     *     is_applied: bool,
     *     is_custom: bool,
     *     is_toggleable: bool,
     *     sort_order: int
     * }
     */
    private function row(
        ?string $deductionTypeId,
        ?string $code,
        string $name,
        string $method,
        ?string $rate,
        ?string $fixedAmount,
        BigDecimal $baseAmount,
        BigDecimal $amount,
        ?BigDecimal $overrideAmount,
        bool $isInterest,
        bool $isApplied,
        bool $isCustom,
        bool $isToggleable,
        int $sortOrder,
    ): array {
        return [
            'deduction_type_id' => $deductionTypeId,
            'code' => $code,
            'name' => $name,
            'calculation_method' => $method,
            'rate' => $rate,
            'fixed_amount' => $fixedAmount,
            'base_amount' => $baseAmount->__toString(),
            'amount' => $amount->__toString(),
            'override_amount' => $overrideAmount?->__toString(),
            'is_interest' => $isInterest,
            'is_applied' => $isApplied,
            'is_custom' => $isCustom,
            'is_toggleable' => $isToggleable,
            'sort_order' => $sortOrder,
        ];
    }
}
