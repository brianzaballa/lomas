<?php

namespace App\Domain\Payments;

use App\Domain\Nodes\NodeIdentity;
use App\Domain\Nodes\SequenceAllocator;
use App\Domain\Settings\SequenceFormatter;
use App\Models\Sequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * Produces the business identifier of a payment, e.g. "PY-MNL1-2026-000001".
 *
 * The sequence is drawn from this node's own counter, so a payment can be
 * recorded with zero connectivity and two nodes never issue the same number.
 * The unique index on payment_number is the final guard. See AGENTS.md sections
 * 22, 69.3.
 */
class PaymentNumberGenerator
{
    /**
     * Payments have no configurable format in system settings, so the node
     * qualified default is used.
     */
    protected const FORMAT = 'PY-{node}-{year}-{seq:6}';

    public function __construct(
        private readonly SequenceAllocator $sequences,
        private readonly SequenceFormatter $formatter,
        private readonly NodeIdentity $nodes,
    ) {}

    public function next(): string
    {
        $sequence = $this->sequences->next(Sequence::ENTITY_PAYMENT);

        return $this->formatter->format(self::FORMAT, $sequence, Date::now(), $this->nodes->code());
    }

    /**
     * Draw a contiguous block of numbers for payments dated on the same day.
     *
     * The counter is advanced once, so importing or seeding many payments does
     * not pay for a locked transaction per number.
     *
     * @return list<string>
     */
    public function nextBlock(int $count, CarbonInterface $date): array
    {
        if ($count <= 0) {
            return [];
        }

        $start = $this->sequences->nextBlock(Sequence::ENTITY_PAYMENT, $count);
        $nodeCode = $this->nodes->code();

        $numbers = [];
        for ($i = 0; $i < $count; $i++) {
            $numbers[] = $this->formatter->format(self::FORMAT, $start + $i, $date, $nodeCode);
        }

        return $numbers;
    }
}
