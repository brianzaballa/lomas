<?php

namespace App\Concerns;

use App\Domain\Loans\DeductionSelection;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * The loan encoding input shared by the preview endpoint and the save form.
 *
 * Both must accept exactly the same thing, because the preview is a promise
 * about what will be stored. Keeping the rules and the mapping in one trait is
 * what stops the preview and the saved draft from drifting apart.
 *
 * The product decides which deductions exist. The encoder may switch any of
 * them off for this loan and may add a custom fixed charge, see AGENTS.md
 * section 63.1.3.
 */
trait HandlesLoanEncodingRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function loanEncodingRules(): array
    {
        return [
            'interest_override_amount' => ['nullable', 'numeric', 'gte:0'],
            'excluded_deduction_codes' => ['nullable', 'array', 'max:50'],
            'excluded_deduction_codes.*' => ['string', 'max:32'],
            'custom_deductions' => ['nullable', 'array', 'max:'.DeductionSelection::MAX_CUSTOM_DEDUCTIONS],
            'custom_deductions.*.name' => ['required', 'string', 'max:100'],
            'custom_deductions.*.amount' => ['required', 'numeric', 'gte:0'],
        ];
    }

    /**
     * The interest amount the encoder entered, or null to use the product rate.
     */
    public function interestOverrideAmount(): ?string
    {
        $value = $this->input('interest_override_amount');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return BigDecimal::of(trim($value))->toScale(2, RoundingMode::HalfUp)->__toString();
    }

    /**
     * What the encoder chose for this loan, on top of the product defaults.
     */
    public function deductionSelection(): DeductionSelection
    {
        return DeductionSelection::fromInput(
            $this->input('excluded_deduction_codes'),
            $this->input('custom_deductions'),
        );
    }
}
