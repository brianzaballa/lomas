<?php

namespace App\Actions\LoanProducts;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Models\LoanProduct;
use Illuminate\Support\Facades\DB;

/**
 * Creates a loan product and records it in the audit trail.
 *
 * A product is configuration, so an edit is allowed at any time. Loans keep
 * their own copy of every amount, which is what protects released loans.
 */
class CreateLoanProduct
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): LoanProduct
    {
        return DB::transaction(function () use ($attributes): LoanProduct {
            $product = new LoanProduct($attributes);
            $product->save();

            $this->audit->recordCreated(AuditAction::LOAN_PRODUCT_CREATED, $product, [
                'name' => $product->name,
                'code' => $product->code,
                'term' => $product->term,
                'term_unit' => $product->term_unit->value,
                'payment_frequency' => $product->payment_frequency->value,
                'exclude_sundays' => $product->exclude_sundays,
                'exclude_saturdays' => $product->exclude_saturdays,
                'release_date_is_day_one' => $product->release_date_is_day_one,
                'interest_method' => $product->interest_method->value,
                'interest_rate' => $product->interest_rate,
                'is_active' => $product->is_active,
            ]);

            return $product;
        });
    }
}
