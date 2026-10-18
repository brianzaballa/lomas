<?php

namespace App\Models;

use App\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The link between a loan product and a deduction type it charges.
 *
 * A product decides which deductions exist for its loans. The link itself is
 * the whole configuration: a type that is not linked is not charged. Which
 * types exist, and whether a type is globally active, is configuration in its
 * own right, see AGENTS.md section 7.
 *
 * @property string $id
 * @property string $loan_product_id
 * @property string $loan_deduction_type_id
 * @property int $sort_order
 */
#[Fillable([
    'loan_product_id',
    'loan_deduction_type_id',
    'sort_order',
])]
class LoanProductDeductionType extends Model
{
    use HasUuidKey;

    /**
     * @return BelongsTo<LoanProduct, $this>
     */
    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    /**
     * @return BelongsTo<LoanDeductionType, $this>
     */
    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(LoanDeductionType::class, 'loan_deduction_type_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
