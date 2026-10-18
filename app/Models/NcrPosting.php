<?php

namespace App\Models;

use App\Concerns\BelongsToNode;
use App\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The posting of one Notes Collection Report period.
 *
 * A posted period is the sign-off for its figures, and it soft-locks the
 * payments dated inside it: nothing is staged, posted or reversed in the
 * period until it is unposted. Both transitions are audited. See AGENTS.md
 * sections 47 and 48.
 *
 * @property string $id
 * @property string $node_id
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property string $status
 * @property int|null $posted_by
 * @property Carbon|null $posted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'node_id',
    'period_start',
    'period_end',
    'status',
    'posted_by',
    'posted_at',
])]
class NcrPosting extends Model
{
    use BelongsToNode;
    use HasUuidKey;

    public const STATUS_OPEN = 'open';

    public const STATUS_POSTED = 'posted';

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    /**
     * Whether a date falls inside this posting's period.
     */
    public function covers(string $date): bool
    {
        return $date >= $this->period_start->toDateString()
            && $date <= $this->period_end->toDateString();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'posted_at' => 'datetime',
        ];
    }
}
