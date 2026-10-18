<?php

namespace App\Actions\LoanProducts;

use App\Models\LoanDeductionType;
use App\Models\LoanProduct;
use App\Models\LoanProductDeductionType;

/**
 * Sets the deduction types a loan product charges.
 *
 * The form carries the whole desired set, so anything missing is unlinked. That
 * is safe because a loan copies its own deduction snapshot when it is encoded,
 * so unlinking a type never changes a loan that already carries it.
 *
 * Laravel's sync() is not used because the pivot table carries a uuid key, and
 * sync() cannot assign one.
 */
class SyncLoanProductDeductions
{
    /**
     * @param  list<string>  $deductionTypeIds  in the order the product charges them
     */
    public function handle(LoanProduct $product, array $deductionTypeIds): void
    {
        $kept = [];

        foreach ($deductionTypeIds as $position => $deductionTypeId) {
            $link = LoanProductDeductionType::query()->firstOrNew([
                'loan_product_id' => $product->getKey(),
                'loan_deduction_type_id' => $deductionTypeId,
            ]);

            $link->sort_order = $position + 1;
            $link->save();

            $kept[] = $link->getKey();
        }

        LoanProductDeductionType::query()
            ->where('loan_product_id', $product->getKey())
            ->whereNotIn('id', $kept)
            ->delete();

        // The caller may have loaded the relation to compare the old set, so it
        // is dropped here rather than handing back a stale list.
        $product->unsetRelation('deductionTypes');
    }

    /**
     * The deduction codes a product currently charges, for the audit trail.
     *
     * @return list<string>
     */
    public function codes(LoanProduct $product): array
    {
        return array_values(array_map(
            static fn (LoanDeductionType $type): string => $type->code,
            $product->deductionTypes->sortBy('pivot.sort_order')->all(),
        ));
    }
}
