<?php

namespace App\Domain\Loans;

use App\Domain\Nodes\NodeIdentity;
use App\Domain\Settings\SequenceFormatter;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanDeduction;
use App\Models\LoanProduct;
use App\Models\Sequence;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Creates a draft loan from the encoded borrower, product and deductions.
 */
class LoanDraftService
{
    public function __construct(
        private readonly LoanCalculator $calculator,
        private readonly NodeIdentity $nodes,
        private readonly SequenceFormatter $formatter,
    ) {}

    /**
     * @param  array{
     *     borrower_id: string,
     *     loan_product_id: string,
     *     area_id?: string|null,
     *     gross_loan_amount: string,
     *     application_date: string,
     *     interest_override_amount?: string|null,
     *     deduction_selection?: DeductionSelection
     * }  $attributes
     */
    public function create(array $attributes): Loan
    {
        return DB::transaction(function () use ($attributes): Loan {
            $borrower = Borrower::query()->findOrFail($attributes['borrower_id']);
            $product = LoanProduct::query()->findOrFail($attributes['loan_product_id']);

            $preview = $this->calculator->preview(
                $product,
                $attributes['gross_loan_amount'],
                $attributes['interest_override_amount'] ?? null,
                $attributes['deduction_selection'] ?? DeductionSelection::none(),
            );

            $loan = Loan::query()->create([
                'node_id' => $this->nodes->id(),
                'loan_number' => $this->nextLoanNumber(),
                'borrower_id' => $borrower->getKey(),
                'loan_product_id' => $product->getKey(),
                'area_id' => $attributes['area_id'] ?? $borrower->default_area_id,
                'status' => Loan::STATUS_DRAFT,
                'application_date' => $attributes['application_date'],
                'gross_loan_amount' => $preview['gross_loan_amount'],
                'interest_amount' => $preview['interest_amount'],
                'deductions_amount' => $preview['deductions_amount'],
                'net_proceeds_amount' => $preview['net_proceeds_amount'],
                'total_amount_payable' => $preview['total_amount_payable'],
            ]);

            $this->snapshotDeductions($loan, $preview['deductions']);

            return $loan;
        });
    }

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
     *     deductions: list<array<string, mixed>>
     * }
     */
    public function preview(
        LoanProduct $product,
        string $grossLoanAmount,
        ?string $interestOverrideAmount = null,
        ?DeductionSelection $selection = null,
    ): array {
        return $this->calculator->preview($product, $grossLoanAmount, $interestOverrideAmount, $selection);
    }

    /**
     * Copy the calculation into the loan's own deduction rows.
     *
     * A deduction the encoder switched off for this loan gets no row, which is
     * how "not applied" is expressed. Everything that was charged is copied
     * verbatim, so a later change to the product or to a deduction type never
     * recalculates this loan. See AGENTS.md sections 8 and 63.1.3.
     *
     * @param  list<array{
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
     * }>  $deductions
     */
    private function snapshotDeductions(Loan $loan, array $deductions): void
    {
        $nodeId = $loan->node_id;

        foreach ($deductions as $deduction) {
            if (! $deduction['is_applied']) {
                continue;
            }

            LoanDeduction::query()->create([
                'node_id' => $nodeId,
                'loan_id' => $loan->getKey(),
                'deduction_type_id' => $deduction['deduction_type_id'],
                'name' => $deduction['name'],
                'code' => $deduction['code'],
                'calculation_method' => $deduction['calculation_method'],
                'rate' => $deduction['rate'],
                'fixed_amount' => $deduction['fixed_amount'],
                'base_amount' => $deduction['base_amount'],
                'amount' => $deduction['amount'],
                'override_amount' => $deduction['override_amount'],
                'is_interest' => $deduction['is_interest'],
                'sort_order' => $deduction['sort_order'],
            ]);
        }
    }

    private function nextLoanNumber(): string
    {
        $sequence = Sequence::query()->firstOrCreate(
            ['node_id' => $this->nodes->id(), 'entity' => Sequence::ENTITY_LOAN],
            ['next_value' => 1],
        );

        $number = $sequence->next_value;
        $sequence->increment('next_value');

        return $this->formatter->format(
            'LN-{node}-{year}-{seq:6}',
            $number,
            Date::now(),
            $this->nodes->code(),
        );
    }
}
