<?php

namespace App\Models;

use App\Concerns\BelongsToNode;
use App\Concerns\HasUuidKey;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A collection day, the unit a set of payments is posted as.
 *
 * While the day is open, payments may be added or removed. Posting closes the
 * day as a unit; afterwards nothing on it may change except by a reversal. See
 * AGENTS.md sections 63.1.7 and 63.1.8.
 *
 * @property string $id
 * @property string $node_id
 * @property Carbon $business_date
 * @property string $status
 * @property int|null $prepared_by
 * @property Carbon|null $prepared_at
 * @property int|null $posted_by
 * @property Carbon|null $posted_at
 * @property int $payment_count
 * @property string $total_amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'node_id',
    'business_date',
    'status',
    'prepared_by',
    'prepared_at',
    'posted_by',
    'posted_at',
    'payment_count',
    'total_amount',
])]
class Collection extends Model
{
    use BelongsToNode;
    use HasUuidKey;

    public const STATUS_OPEN = 'open';

    public const STATUS_POSTED = 'posted';

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('created_at')->orderBy('id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    /**
     * Recompute the header totals from the payments this day actually holds.
     *
     * Counting only posted payments keeps the header reconciled with the loan
     * balances during preparation and after a reversal. See section 38.
     */
    public function refreshTotals(): void
    {
        $posted = $this->payments()->where('status', Payment::STATUS_POSTED)->pluck('amount');

        $total = Money::sum(...$posted->map(fn ($amount): Money => Money::of((string) $amount))->all());

        $this->forceFill([
            'payment_count' => $posted->count(),
            'total_amount' => $total->toDecimalString(),
        ])->save();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'prepared_at' => 'datetime',
            'posted_at' => 'datetime',
            'payment_count' => 'integer',
            'total_amount' => 'decimal:2',
        ];
    }
}
