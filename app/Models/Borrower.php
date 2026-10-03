<?php

namespace App\Models;

use App\Concerns\BelongsToNode;
use App\Concerns\HasUuidKey;
use App\Enums\BorrowerStatus;
use Database\Factories\BorrowerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A person who borrows from the business. Reused across every loan.
 *
 * @property string $id
 * @property string $node_id
 * @property string $borrower_number
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $suffix
 * @property Carbon|null $birth_date
 * @property string $mobile_number
 * @property string $address
 * @property BorrowerStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'node_id',
    'borrower_number',
    'first_name',
    'middle_name',
    'last_name',
    'suffix',
    'birth_date',
    'mobile_number',
    'address',
    'status',
])]
class Borrower extends Model
{
    /**
     * A borrower key is a uuid assigned by the node that registered the person.
     */
    use BelongsToNode;

    /** @use HasFactory<BorrowerFactory> */
    use HasFactory;

    use HasUuidKey;

    /**
     * The name as shown in lists and on printed documents.
     *
     * Middle name and suffix are only rendered when they are known, so the
     * label never contains empty gaps.
     */
    public function fullName(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name])
            ->filter()
            ->implode(' ')
            .($this->suffix !== null && $this->suffix !== '' ? ' '.$this->suffix : '');
    }

    /**
     * Search by borrower number, name or mobile number, as listed in
     * AGENTS.md section 27.
     *
     * Every word of the search term has to match one of the fields, so
     * "Dela Cruz 0917" narrows the result instead of widening it. Wildcard
     * characters typed by the user are escaped so a search cannot become a
     * full table scan.
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

        $words = preg_split('/\s+/', $terms) ?: [];

        foreach (array_slice($words, 0, 5) as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';

            $query->where(function (Builder $query) use ($like): void {
                $query->where('borrower_number', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('middle_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('mobile_number', 'like', $like);
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
            'birth_date' => 'date',
            'status' => BorrowerStatus::class,
        ];
    }
}
