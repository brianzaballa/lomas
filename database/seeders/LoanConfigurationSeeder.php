<?php

namespace Database\Seeders;

use App\Enums\DeductionCalculationMethod;
use App\Enums\InterestMethod;
use App\Enums\PaymentFrequency;
use App\Enums\TermUnit;
use App\Models\LoanDeductionType;
use App\Models\LoanProduct;
use Illuminate\Database\Seeder;

/**
 * The loan configuration the business starts with.
 *
 * These rows are configuration, not sample transactions. There are no demo
 * borrowers, loans, payments or schedules here on purpose.
 *
 * The values come from the answers of record in AGENTS.md section 63.1:
 *
 *   - 100 valid collection days, collected daily
 *   - Sunday excluded, Saturday collected (Monday to Saturday)
 *   - the first collection is the day after the release date
 *   - interest is 10% of the original loan amount, withheld at release
 */
class LoanConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedLoanProduct();
        $this->seedDeductionTypes();
    }

    protected function seedLoanProduct(): void
    {
        LoanProduct::query()->firstOrCreate(
            ['code' => 'LOAN-100D'],
            [
                'name' => '100-Day Loan',
                'description' => '100 valid collection days, collected Monday to Saturday. Interest is withheld at release, so the schedule collects the principal only.',
                'term' => 100,
                'term_unit' => TermUnit::CollectionDays,
                'payment_frequency' => PaymentFrequency::Daily,
                'exclude_sundays' => true,
                'exclude_saturdays' => false,
                'release_date_is_day_one' => false,
                'interest_method' => InterestMethod::PercentageOfLoanAmount,
                'interest_rate' => '10.00',
                'is_active' => true,
            ],
        );
    }

    /**
     * Documentary stamp and passbook only.
     *
     * Interest is deliberately absent: the rate lives on the loan product. Any
     * other deduction, such as a processing fee or insurance, is added here or
     * directly on a single loan when it is needed.
     */
    protected function seedDeductionTypes(): void
    {
        $types = [
            [
                'code' => 'DOC_STAMP',
                'name' => 'Documentary Stamp',
                'description' => 'Percentage of the loan amount, withheld at release.',
                'calculation_method' => DeductionCalculationMethod::PercentageOfLoanAmount,
                'rate' => '1.00',
                'fixed_amount' => null,
                'sort_order' => 1,
            ],
            [
                'code' => 'PASSBOOK',
                'name' => 'Passbook',
                'description' => 'Fixed amount, withheld at release.',
                'calculation_method' => DeductionCalculationMethod::FixedAmount,
                'rate' => null,
                'fixed_amount' => '50.00',
                'sort_order' => 2,
            ],
        ];

        foreach ($types as $type) {
            LoanDeductionType::query()->firstOrCreate(
                ['code' => $type['code']],
                [...$type, 'is_active' => true],
            );
        }
    }
}
