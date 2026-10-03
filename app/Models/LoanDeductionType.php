<?php

namespace App\Models;

use App\Concerns\HasUuidKey;
use App\Enums\DeductionCalculationMethod;
use Database\Factories\LoanDeductionTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A configurable deduction, e.g. documentary stamp or passbook.
 *
 * A deduction type is configuration. The amount a loan was actually charged is
 * copied into loan_deductions when the loan is released, so a later change to
 * this row never alters a released loan.
 *
 * Interest is not stored here. The rate belongs to the loan product, because it
 * is the product that defines how much a loan costs.
 *
 * @property string $id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property DeductionCalculationMethod $calculation_method
 * @property string|null $rate
 * @property string|null $fixed_amount
 * @property bool $is_active
 * @property int $sort_order
 * @property int $version
 * @property Carbon|null $distributed_at
 */
#[Fillable([
    'name',
    'code',
    'description',
    'calculation_method',
    'rate',
    'fixed_amount',
    'is_active',
    'sort_order',
])]
class LoanDeductionType extends Model
{
    /** @use HasFactory<LoanDeductionTypeFactory> */
    use HasFactory;

    /**
     * Configuration rows are keyed by a uuid assigned by central, which is the
     * node that owns configuration. See AGENTS.md sections 69.3 and 69.4.
     */
    use HasUuidKey;

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The rate as it is shown to staff, e.g. "1%" or "7.5%".
     */
    public function rateLabel(): string
    {
        if ($this->rate === null) {
            return '';
        }

        $rate = rtrim(rtrim($this->rate, '0'), '.');

        return ($rate === '' ? '0' : $rate).'%';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'fixed_amount' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'version' => 'integer',
            'distributed_at' => 'datetime',
            'calculation_method' => DeductionCalculationMethod::class,
        ];
    }
}
