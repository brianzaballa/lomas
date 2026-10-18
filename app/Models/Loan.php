<?php

namespace App\Models;

use App\Concerns\BelongsToNode;
use App\Concerns\HasUuidKey;
use Database\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A loan draft encoded from a borrower and a product.
 *
 * Phase 4 stops at encoding and previewing the loan. Release, schedule
 * generation and collections arrive later, so this model keeps only the
 * minimal draft fields needed to encode the business decision and preview the
 * financial result.
 *
 * @property string $id
 * @property string $node_id
 * @property string $loan_number
 * @property string $borrower_id
 * @property string $loan_product_id
 * @property string $status
 * @property Carbon|null $application_date
 * @property Carbon|null $release_date
 * @property string $gross_loan_amount
 * @property string $interest_amount
 * @property string $deductions_amount
 * @property string $net_proceeds_amount
 * @property string $total_amount_payable
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'node_id',
    'loan_number',
    'borrower_id',
    'loan_product_id',
    'area_id',
    'status',
    'application_date',
    'release_date',
    'gross_loan_amount',
    'interest_amount',
    'deductions_amount',
    'net_proceeds_amount',
    'total_amount_payable',
    'principal_amount',
    'daily_installment_amount',
    'term',
    'term_unit',
    'collection_days',
    'exclude_sundays',
    'exclude_saturdays',
    'release_date_is_day_one',
    'interest_method',
    'interest_rate',
    'approval_date',
    'approved_by',
    'released_by',
    'cancellation_date',
    'cancelled_by',
    'cancellation_reason',
])]
class Loan extends Model
{
    use BelongsToNode;

    /** @use HasFactory<LoanFactory> */
    use HasFactory;

    use HasUuidKey;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    /**
     * Human labels for every lifecycle status, in navigation order.
     *
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_ACTIVE => 'Active',
    ];

    /**
     * The status as it is shown to staff.
     */
    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    /**
     * @return BelongsTo<Borrower, $this>
     */
    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    /**
     * @return BelongsTo<LoanProduct, $this>
     */
    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    /**
     * The area the loan was encoded for, used for collection grouping.
     *
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    /**
     * The node that owns the loan, used to derive the area grouping.
     *
     * @return BelongsTo<Node, $this>
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'node_id');
    }

    /**
     * The deductions charged to this loan, exactly as they were calculated.
     *
     * This is a snapshot, not a live read of the configuration. See
     * AGENTS.md section 8.
     *
     * @return HasMany<LoanDeduction, $this>
     */
    public function deductions(): HasMany
    {
        return $this->hasMany(LoanDeduction::class)->orderBy('sort_order');
    }

    /**
     * The frozen collection schedule, generated once at release.
     *
     * The schedule is never regenerated. It records the scheduled amounts only;
     * paid and remaining amounts are sums of payment allocations. See AGENTS.md
     * sections 14 and 63.1.9.
     *
     * @return HasMany<LoanAmortization, $this>
     */
    public function amortizations(): HasMany
    {
        return $this->hasMany(LoanAmortization::class)->orderBy('installment_number');
    }

    /**
     * The payments received against this loan, newest first for display.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderByDesc('payment_date')->orderByDesc('created_at');
    }

    /**
     * Search by loan number or by the borrower it belongs to.
     *
     * Every word of the search term has to match, so a search narrows rather
     * than widens. Wildcard characters are escaped. See AGENTS.md section 27.
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
                $query->where('loan_number', 'like', $like)
                    ->orWhereHas('borrower', fn (Builder $borrower) => $borrower
                        ->where('borrower_number', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('middle_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like))
                    ->orWhereHas('node.area', fn (Builder $area) => $area
                        ->where('code', 'like', $like)
                        ->orWhere('name', 'like', $like));
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
            'application_date' => 'date',
            'release_date' => 'date',
            'gross_loan_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'deductions_amount' => 'decimal:2',
            'net_proceeds_amount' => 'decimal:2',
            'total_amount_payable' => 'decimal:2',
        ];
    }
}
