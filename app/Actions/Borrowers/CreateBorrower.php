<?php

namespace App\Actions\Borrowers;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Domain\Borrowers\BorrowerNumberGenerator;
use App\Enums\BorrowerStatus;
use App\Models\Borrower;
use Illuminate\Support\Facades\DB;

/**
 * Registers a borrower and assigns the business number.
 */
class CreateBorrower
{
    public function __construct(
        private readonly BorrowerNumberGenerator $numbers,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *     first_name: string,
     *     middle_name?: string|null,
     *     last_name: string,
     *     suffix?: string|null,
     *     birth_date?: string|null,
     *     mobile_number: string,
     *     address: string,
     *     status?: string|null
     * }  $attributes
     */
    public function handle(array $attributes): Borrower
    {
        return DB::transaction(function () use ($attributes): Borrower {
            $borrower = new Borrower([
                ...$attributes,
                'borrower_number' => $this->numbers->next(),
                'status' => BorrowerStatus::from($attributes['status'] ?? BorrowerStatus::Active->value),
            ]);

            $borrower->save();

            $this->audit->recordCreated(AuditAction::BORROWER_CREATED, $borrower, [
                'borrower_number' => $borrower->borrower_number,
                'name' => $borrower->fullName(),
                'mobile_number' => $borrower->mobile_number,
                'status' => $borrower->status->value,
            ]);

            return $borrower;
        });
    }
}
