<?php

namespace App\Actions\DeductionTypes;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Models\LoanDeductionType;
use Illuminate\Support\Facades\DB;

/**
 * Creates a deduction type and records it in the audit trail.
 */
class CreateDeductionType
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): LoanDeductionType
    {
        return DB::transaction(function () use ($attributes): LoanDeductionType {
            $type = new LoanDeductionType($attributes);
            $type->save();

            $this->audit->recordCreated(AuditAction::DEDUCTION_TYPE_CREATED, $type, [
                'name' => $type->name,
                'code' => $type->code,
                'calculation_method' => $type->calculation_method->value,
                'rate' => $type->rate,
                'fixed_amount' => $type->fixed_amount,
                'is_active' => $type->is_active,
                'sort_order' => $type->sort_order,
            ]);

            return $type;
        });
    }
}
