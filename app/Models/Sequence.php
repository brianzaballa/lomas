<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The next business number a node will issue for one kind of record.
 *
 * Each node draws from its own row while offline. Central never allocates on a
 * branch's behalf. See AGENTS.md section 69.3.
 *
 * @property int $id
 * @property string $node_id
 * @property string $entity
 * @property int $next_value
 */
#[Fillable(['node_id', 'entity', 'next_value'])]
class Sequence extends Model
{
    public const ENTITY_BORROWER = 'borrower';

    public const ENTITY_LOAN = 'loan';

    public const ENTITY_PAYMENT = 'payment';

    /**
     * Every entity that may hold a counter.
     *
     * @return list<string>
     */
    public static function entities(): array
    {
        return [
            self::ENTITY_BORROWER,
            self::ENTITY_LOAN,
            self::ENTITY_PAYMENT,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'next_value' => 'integer',
        ];
    }
}
