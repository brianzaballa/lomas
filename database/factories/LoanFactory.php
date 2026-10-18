<?php

namespace Database\Factories;

use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gross = '10000.00';

        return [
            'borrower_id' => Borrower::factory(),
            'loan_product_id' => LoanProduct::factory(),
            'status' => Loan::STATUS_DRAFT,
            'application_date' => now()->toDateString(),
            'release_date' => null,
            'gross_loan_amount' => $gross,
            'interest_amount' => '1000.00',
            'deductions_amount' => '1000.00',
            'net_proceeds_amount' => '9000.00',
            'total_amount_payable' => $gross,
        ];
    }
}
