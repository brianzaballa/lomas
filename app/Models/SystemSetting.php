<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The single row of application wide configuration.
 *
 * @property int $id
 * @property string $business_name
 * @property string|null $business_address
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string $currency
 * @property string $currency_symbol
 * @property string $loan_number_format
 * @property string $borrower_number_format
 * @property string $ncr_period_mode
 * @property int $version
 * @property Carbon|null $distributed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'business_name',
    'business_address',
    'contact_email',
    'contact_phone',
    'currency',
    'currency_symbol',
    'loan_number_format',
    'borrower_number_format',
    'ncr_period_mode',
])]
class SystemSetting extends Model
{
    /**
     * The database row that holds the system wide configuration.
     */
    public const SINGLETON_ID = 1;

    /**
     * Format placeholders understood by the sequence formatter.
     */
    public const FORMAT_PLACEHOLDERS = ['{year}', '{month}', '{node}', '{seq}', '{seq:N}'];

    /**
     * The Notes Collection Report posts in Monday to Saturday weeks.
     */
    public const NCR_PERIOD_MODE_WEEKLY = 'weekly';

    /**
     * The Notes Collection Report posts in 1-10, 11-20 and 21 to end of month.
     */
    public const NCR_PERIOD_MODE_DECILE = 'decile';

    /**
     * The accepted Notes Collection Report period modes.
     *
     * @var list<string>
     */
    public const NCR_PERIOD_MODES = [
        self::NCR_PERIOD_MODE_WEEKLY,
        self::NCR_PERIOD_MODE_DECILE,
    ];

    /**
     * The default formats used when a fresh installation is seeded.
     */
    public const DEFAULTS = [
        'business_name' => 'LoMaS',
        'business_address' => null,
        'contact_email' => null,
        'contact_phone' => null,
        'currency' => 'PHP',
        'currency_symbol' => 'PHP',
        // Every business number carries the node code, so two offline nodes
        // never issue the same number. See AGENTS.md sections 21, 22 and 69.3.
        'loan_number_format' => 'LN-{node}-{year}-{seq:6}',
        'borrower_number_format' => 'BR-{node}-{seq:6}',
        'ncr_period_mode' => self::NCR_PERIOD_MODE_WEEKLY,
    ];
}
