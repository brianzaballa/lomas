<?php

namespace App\Models;

use App\Concerns\BelongsToNode;
use App\Concerns\HasUuidKey;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A single cash receipt against a loan.
 *
 * A payment is a historical transaction and is never deleted. It is staged as
 * pending while its collection day is open, becomes posted when the day is
 * posted, and can only be corrected by a reversal, which keeps the row and
 * stops its allocations counting. See AGENTS.md sections 29, 31, 32, 33 and
 * 63.1.5 to 63.1.8.
 *
 * @property string $id
 * @property string $node_id
 * @property string $payment_number
 * @property string $loan_id
 * @property string $collection_id
 * @property Carbon $payment_date
 * @property string $amount
 * @property string $allocated_amount
 * @property string $unapplied_amount
 * @property string $payment_method
 * @property string|null $reference_number
 * @property int|null $received_by
 * @property string $status
 * @property string|null $reversal_of_payment_id
 * @property Carbon|null $reversed_at
 * @property int|null $reversed_by
 * @property string|null $reversal_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'node_id',
    'payment_number',
    'loan_id',
    'collection_id',
    'payment_date',
    'amount',
    'allocated_amount',
    'unapplied_amount',
    'payment_method',
    'reference_number',
    'received_by',
    'status',
    'reversal_of_payment_id',
    'reversed_at',
    'reversed_by',
    'reversal_reason',
])]
class Payment extends Model
{
    use BelongsToNode;
    use HasUuidKey;

    public const STATUS_PENDING = 'pending';

    public const STATUS_POSTED = 'posted';

    public const STATUS_REVERSED = 'reversed';

    public const METHOD_CASH = 'cash';

    /**
     * Human labels for every payment status.
     *
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_POSTED => 'Posted',
        self::STATUS_REVERSED => 'Reversed',
    ];

    /**
     * The status as it is shown to staff.
     */
    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    /**
     * The methods a collector may record.
     *
     * @return list<string>
     */
    public static function methods(): array
    {
        return [
            self::METHOD_CASH,
            'gcash',
            'bank_transfer',
            'check',
        ];
    }

    /**
     * @return BelongsTo<Loan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * @return BelongsTo<Collection, $this>
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * A payment counts toward a balance only once it is posted.
     *
     * Pending payments sit in an open collection day, and a reversed payment is
     * excluded, so both leave loan balances and reports untouched. See sections
     * 38 and 63.1.8.
     */
    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }

    /**
     * The excess over the amount owed, kept on the payment rather than pushed
     * into a future installment. See section 63.1.6.
     */
    public function unapplied(): Money
    {
        return Money::of($this->unapplied_amount);
    }

    /**
     * Search by payment number, loan number or the borrower the loan belongs to.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSearch(Builder $query, ?string $terms): Builder
    {
        $terms = trim((string) $terms);

        if ($terms === '') {
            return $query;
        }

        foreach (array_slice(preg_split('/\s+/', $terms) ?: [], 0, 5) as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';

            $query->where(function (Builder $query) use ($like): void {
                $query->where('payment_number', 'like', $like)
                    ->orWhereHas('loan', fn (Builder $loan) => $loan
                        ->where('loan_number', 'like', $like)
                        ->orWhereHas('borrower', fn (Builder $borrower) => $borrower
                            ->where('borrower_number', 'like', $like)
                            ->orWhere('first_name', 'like', $like)
                            ->orWhere('middle_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)));
            });
        }

        return $query;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'allocated_amount' => 'decimal:2',
            'unapplied_amount' => 'decimal:2',
            'reversed_at' => 'datetime',
        ];
    }
}
