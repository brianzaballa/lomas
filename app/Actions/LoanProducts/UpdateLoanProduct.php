<?php

namespace App\Actions\LoanProducts;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Models\LoanProduct;
use Illuminate\Support\Facades\DB;

/**
 * Updates a loan product and records the previous values.
 *
 * Changing a product never touches a released loan. The loan keeps the amounts
 * and the schedule it was released with.
 */
class UpdateLoanProduct
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(LoanProduct $product, array $attributes): LoanProduct
    {
        return DB::transaction(function () use ($product, $attributes): LoanProduct {
            $old = $this->snapshot($product);

            $product->fill($attributes)->save();

            $new = $this->snapshot($product);

            if ($old !== $new) {
                $this->audit->recordUpdated(AuditAction::LOAN_PRODUCT_UPDATED, $product, $old, $new);
            }

            return $product;
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(LoanProduct $product): array
    {
        return [
            'name' => $product->name,
            'code' => $product->code,
            'description' => $product->description,
            'term' => $product->term,
            'term_unit' => $product->term_unit->value,
            'payment_frequency' => $product->payment_frequency->value,
            'exclude_sundays' => $product->exclude_sundays,
            'exclude_saturdays' => $product->exclude_saturdays,
            'release_date_is_day_one' => $product->release_date_is_day_one,
            'interest_method' => $product->interest_method->value,
            'interest_rate' => $product->interest_rate,
            'is_active' => $product->is_active,
        ];
    }
}
