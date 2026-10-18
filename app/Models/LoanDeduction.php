<?php

namespace App\Models;

use App\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One deduction charged to one loan, exactly as it was calculated.
 *
 * This is the snapshot required by AGENTS.md section 8. The name, code, method,
 * rate, base amount and result are all copied here, so the original
 * calculation stays reproducible and a later settings change never touches a
 * loan that was encoded under the old configuration.
 *
 * A row may have no deduction_type_id. Interest is not a deduction type, and a
 * custom charge added to a single loan belongs to no product at all.
 *
 * @property string $id
 * @property string $node_id
 * @property string $loan_id
 * @property string|null $deduction_type_id
 * @property string $name
 * @property string|null $code
 * @property string $calculation_method
 * @property string|null $rate
 * @property string|null $fixed_amount
 * @property string $base_amount
 * @property string $amount
 * @property string|null $override_amount
 * @property bool $is_interest
 * @property int $sort_order
 * @property Carbon|null $created_at
 */
#[Fillable([
    'node_id',
    'loan_id',
    'deduction_type_id',
    'name',
    'code',
    'calculation_method',
    'rate',
    'fixed_amount',
    'base_amount',
    'amount',
    'override_amount',
    'is_interest',
    'sort_order',
])]
class LoanDeduction extends Model
{
    use HasUuidKey;

    /**
     * @return BelongsTo<Loan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * @return BelongsTo<LoanDeductionType, $this>
     */
    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(LoanDeductionType::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'fixed_amount' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'override_amount' => 'decimal:2',
            'is_interest' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
