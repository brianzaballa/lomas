<?php

namespace App\Models;

use App\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One installation of LoMaS. A branch is a complete install; central is the same
 * plus consolidation. See AGENTS.md section 69.2.
 *
 * @property string $id
 * @property string|null $area_id
 * @property string $code
 * @property string $name
 * @property string $role
 * @property string|null $public_key
 * @property string $timezone
 * @property bool $is_active
 * @property Carbon|null $last_seen_at
 * @property int $last_applied_serial
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['area_id', 'code', 'name', 'role', 'public_key', 'timezone', 'is_active', 'last_seen_at', 'last_applied_serial'])]
class Node extends Model
{
    use HasUuidKey;

    public const ROLE_CENTRAL = 'central';

    public const ROLE_BRANCH = 'branch';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'last_applied_serial' => 'integer',
        ];
    }

    public function isCentral(): bool
    {
        return $this->role === self::ROLE_CENTRAL;
    }

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
