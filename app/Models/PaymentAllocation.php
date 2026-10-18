<?php

namespace App\Models;

use App\Concerns\BelongsToNode;
use App\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How one payment settled one installment.
 *
 * Every peso of a balance is traceable to a payment allocation, so reports
 * reconcile payments to allocations exactly. See AGENTS.md sections 30 and
 * 63.1.5.
 *
 * @property string $id
 * @property string $node_id
 * @property string $payment_id
 * @property string $loan_amortization_id
 * @property string $component
 * @property string $amount
 */
#[Fillable([
    'node_id',
    'payment_id',
    'loan_amortization_id',
    'component',
    'amount',
])]
class PaymentAllocation extends Model
{
    use BelongsToNode;
    use HasUuidKey;

    public const COMPONENT_PRINCIPAL = 'principal';

    public const COMPONENT_INTEREST = 'interest';

    public const COMPONENT_PENALTY = 'penalty';

    public const COMPONENT_OTHER = 'other';

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<LoanAmortization, $this>
     */
    public function amortization(): BelongsTo
    {
        return $this->belongsTo(LoanAmortization::class, 'loan_amortization_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }
}
