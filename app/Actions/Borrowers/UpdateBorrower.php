<?php

namespace App\Actions\Borrowers;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Models\Borrower;
use Illuminate\Support\Facades\DB;

/**
 * Updates a borrower record.
 *
 * Every change is written to the audit trail with the previous values, so an
 * edited name or contact number can always be explained later on.
 */
class UpdateBorrower
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, string|null>  $attributes
     */
    public function handle(Borrower $borrower, array $attributes, ?string $reason = null): Borrower
    {
        // A form that does not submit a status keeps the current one.
        if (($attributes['status'] ?? null) === null) {
            unset($attributes['status']);
        }

        return DB::transaction(function () use ($borrower, $attributes, $reason): Borrower {
            $old = $this->snapshot($borrower);

            $borrower->fill($attributes)->save();

            $new = $this->snapshot($borrower);

            if ($old !== $new) {
                $this->audit->recordUpdated(AuditAction::BORROWER_UPDATED, $borrower, $old, $new, $reason);
            }

            return $borrower;
        });
    }

    /**
     * Only the descriptive fields, never the generated business number.
     *
     * @return array<string, string|null>
     */
    protected function snapshot(Borrower $borrower): array
    {
        return [
            'first_name' => $borrower->first_name,
            'middle_name' => $borrower->middle_name,
            'last_name' => $borrower->last_name,
            'suffix' => $borrower->suffix,
            'birth_date' => $borrower->birth_date?->toDateString(),
            'mobile_number' => $borrower->mobile_number,
            'address' => $borrower->address,
            'status' => $borrower->status->value,
            'default_area_id' => $borrower->default_area_id,
        ];
    }
}
