<?php

namespace App\Models;

use App\Concerns\BelongsToNode;
use App\Concerns\HasUuidKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An immutable record of a change made to a financial or configuration record.
 *
 * The node plus the actor label is the identity of the actor. A bare local
 * user id cannot identify anybody once records from two nodes meet at central,
 * because both nodes have a user 1. See AGENTS.md sections 47 and 69.10.
 *
 * @property string $id
 * @property string $node_id
 * @property int|null $user_id
 * @property string|null $user_label
 * @property string $action
 * @property string|null $auditable_type
 * @property string|null $auditable_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $reason
 * @property string|null $ip_address
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable|null $created_at
 * @property-read User|null $user
 * @property-read Model|null $auditable
 */
#[Fillable(['node_id', 'user_id', 'user_label', 'action', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'reason', 'ip_address', 'occurred_at'])]
class AuditLog extends Model
{
    use BelongsToNode;
    use HasUuidKey;

    public const UPDATED_AT = null;

    /**
     * The business time of the action, which is the writing node's clock and not
     * the moment the entry is received at central. See AGENTS.md section 69.5.
     */
    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            if (blank($log->occurred_at)) {
                $log->occurred_at = CarbonImmutable::now();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
