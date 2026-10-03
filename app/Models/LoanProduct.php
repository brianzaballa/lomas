<?php

namespace App\Models;

use App\Concerns\HasUuidKey;
use App\Domain\Loans\CollectionCalendar;
use App\Enums\InterestMethod;
use App\Enums\PaymentFrequency;
use App\Enums\TermUnit;
use Carbon\CarbonInterface;
use Database\Factories\LoanProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A lending product: the term, the collection calendar and the interest rate.
 *
 * Nothing here is hard coded into the loan engine. A loan always reads the
 * product it was released under, and the released loan keeps its own copy of
 * every amount the product produced.
 *
 * @property string $id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property int $term
 * @property TermUnit $term_unit
 * @property PaymentFrequency $payment_frequency
 * @property bool $exclude_sundays
 * @property bool $exclude_saturdays
 * @property bool $release_date_is_day_one
 * @property InterestMethod $interest_method
 * @property string $interest_rate
 * @property bool $is_active
 * @property int $version
 * @property Carbon|null $distributed_at
 */
#[Fillable([
    'name',
    'code',
    'description',
    'term',
    'term_unit',
    'payment_frequency',
    'exclude_sundays',
    'exclude_saturdays',
    'release_date_is_day_one',
    'interest_method',
    'interest_rate',
    'is_active',
])]
class LoanProduct extends Model
{
    /** @use HasFactory<LoanProductFactory> */
    use HasFactory;

    /**
     * Configuration rows are keyed by a uuid assigned by central, which is the
     * node that owns configuration. See AGENTS.md sections 69.3 and 69.4.
     */
    use HasUuidKey;

    /**
     * Whether the given date is collected on, given the weekend rules.
     *
     * Delegates to the collection calendar so there is exactly one place where
     * weekend logic lives.
     */
    public function isCollectionDay(CarbonInterface $date): bool
    {
        return CollectionCalendar::forProduct($this)->isCollectionDay($date);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The interest rate as it is shown to staff, e.g. "10%" or "7.5%".
     */
    public function interestRateLabel(): string
    {
        $rate = rtrim(rtrim($this->interest_rate, '0'), '.');

        return ($rate === '' ? '0' : $rate).'%';
    }

    /**
     * The term as it is shown to staff, e.g. "100 collection days".
     */
    public function termLabel(): string
    {
        return "{$this->term} ".Str::lower($this->term_unit->label());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'term' => 'integer',
            'exclude_sundays' => 'boolean',
            'exclude_saturdays' => 'boolean',
            'release_date_is_day_one' => 'boolean',
            'interest_rate' => 'decimal:2',
            'is_active' => 'boolean',
            'version' => 'integer',
            'distributed_at' => 'datetime',
            'term_unit' => TermUnit::class,
            'payment_frequency' => PaymentFrequency::class,
            'interest_method' => InterestMethod::class,
        ];
    }
}
