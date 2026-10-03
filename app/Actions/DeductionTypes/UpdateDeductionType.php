<?php

namespace App\Actions\DeductionTypes;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Models\LoanDeductionType;
use Illuminate\Support\Facades\DB;

/**
 * Updates a deduction type and records the previous values.
 *
 * Loans that were already released keep the amount they were charged, because
 * that amount was copied into the loan at release.
 */
class UpdateDeductionType
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(LoanDeductionType $type, array $attributes): LoanDeductionType
    {
        return DB::transaction(function () use ($type, $attributes): LoanDeductionType {
            $old = $this->snapshot($type);

            $type->fill($attributes)->save();

            $new = $this->snapshot($type);

            if ($old !== $new) {
                $this->audit->recordUpdated(AuditAction::DEDUCTION_TYPE_UPDATED, $type, $old, $new);
            }

            return $type;
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(LoanDeductionType $type): array
    {
        return [
            'name' => $type->name,
            'code' => $type->code,
            'description' => $type->description,
            'calculation_method' => $type->calculation_method->value,
            'rate' => $type->rate,
            'fixed_amount' => $type->fixed_amount,
            'is_active' => $type->is_active,
            'sort_order' => $type->sort_order,
        ];
    }
}
