<?php

namespace App\Models;

use App\Concerns\BelongsToNode;
use App\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One installment of a released loan's frozen collection schedule.
 *
 * The schedule is generated once, at release, and never regenerated. This row
 * stores the scheduled amounts; paid_amount and remaining_amount are sums of
 * payment allocations, so they can always be explained from payment history.
 *
 * @property string $id
 * @property string $node_id
 * @property string $loan_id
 * @property int $installment_number
 * @property Carbon $due_date
 * @property string $principal_due
 * @property string $interest_due
 * @property string $total_due
 * @property string $paid_amount
 * @property string $remaining_amount
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'node_id',
    'loan_id',
    'installment_number',
    'due_date',
    'principal_due',
    'interest_due',
    'total_due',
    'paid_amount',
    'remaining_amount',
    'status',
])]
class LoanAmortization extends Model
{
    use BelongsToNode;
    use HasUuidKey;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PARTIALLY_PAID = 'partially_paid';

    public const STATUS_PAID = 'paid';

    /**
     * Human labels for every installment status.
     *
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_PARTIALLY_PAID => 'Partial',
        self::STATUS_PAID => 'Paid',
    ];

    /**
     * The status as it is shown to staff.
     */
    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    /**
     * @return BelongsTo<Loan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * The allocations that settled this installment.
     *
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'loan_amortization_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'installment_number' => 'integer',
            'due_date' => 'date',
            'principal_due' => 'decimal:2',
            'interest_due' => 'decimal:2',
            'total_due' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
        ];
    }
}
