<?php

namespace App\Domain\Borrowers;

use App\Domain\Nodes\NodeIdentity;
use App\Domain\Nodes\SequenceAllocator;
use App\Domain\Settings\SequenceFormatter;
use App\Domain\Settings\SystemSettings;
use App\Models\Borrower;
use App\Models\Sequence;
use RuntimeException;

/**
 * Produces the business identifier of a borrower, e.g. "BR-MNL1-000001".
 *
 * The sequence is drawn from this node's own counter, so a borrower can be
 * registered with zero connectivity and two nodes never issue the same number.
 * The unique index on borrower_number is the final guard. See AGENTS.md
 * sections 21 and 69.3.
 */
class BorrowerNumberGenerator
{
    /**
     * How many times a collision is tolerated before the configured format is
     * reported as unusable. A format without a sequence token cannot produce a
     * new number at all.
     */
    protected const MAX_ATTEMPTS = 100;

    public function __construct(
        private readonly SystemSettings $settings,
        private readonly SequenceFormatter $formatter,
        private readonly SequenceAllocator $sequences,
        private readonly NodeIdentity $nodes,
    ) {}

    public function next(): string
    {
        $format = $this->settings->borrowerNumberFormat();
        $nodeCode = $this->nodes->code();

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $sequence = $this->sequences->next(Sequence::ENTITY_BORROWER);

            $number = $this->formatter->format($format, $sequence, now(), $nodeCode);

            if (! Borrower::query()->where('borrower_number', $number)->exists()) {
                return $number;
            }
        }

        throw new RuntimeException(
            "The configured borrower number format [{$format}] could not produce a unique number.",
        );
    }
}
