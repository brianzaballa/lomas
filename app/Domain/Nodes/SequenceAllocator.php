<?php

namespace App\Domain\Nodes;

use App\Models\Sequence;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Hands out the next business number for a record type.
 *
 * The counter lives on this node's own row, so numbers are issued with zero
 * connectivity and central never allocates on a branch's behalf. See AGENTS.md
 * section 69.3.
 */
class SequenceAllocator
{
    public function __construct(private readonly NodeIdentity $identity) {}

    /**
     * Draw the next value for an entity and advance the counter.
     */
    public function next(string $entity): int
    {
        $this->guardEntity($entity);

        $nodeId = $this->identity->id();

        return DB::transaction(function () use ($nodeId, $entity): int {
            Sequence::query()->firstOrCreate(
                ['node_id' => $nodeId, 'entity' => $entity],
                ['next_value' => 1],
            );

            $sequence = Sequence::query()
                ->where('node_id', $nodeId)
                ->where('entity', $entity)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                throw new InvalidArgumentException("The sequence for [{$entity}] could not be read.");
            }

            $value = (int) $sequence->next_value;

            $sequence->forceFill(['next_value' => $value + 1])->save();

            return $value;
        });
    }

    /**
     * Reserve a contiguous block of values and return the first one.
     *
     * The block is drawn in a single locked transaction, so a bulk writer can
     * issue many numbers without paying for a transaction per number.
     */
    public function nextBlock(string $entity, int $count): int
    {
        if ($count <= 0) {
            throw new InvalidArgumentException('A sequence block must contain at least one value.');
        }

        $this->guardEntity($entity);

        $nodeId = $this->identity->id();

        return DB::transaction(function () use ($nodeId, $entity, $count): int {
            Sequence::query()->firstOrCreate(
                ['node_id' => $nodeId, 'entity' => $entity],
                ['next_value' => 1],
            );

            $sequence = Sequence::query()
                ->where('node_id', $nodeId)
                ->where('entity', $entity)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                throw new InvalidArgumentException("The sequence for [{$entity}] could not be read.");
            }

            $value = (int) $sequence->next_value;

            $sequence->forceFill(['next_value' => $value + $count])->save();

            return $value;
        });
    }

    /**
     * The value that will be handed out next, without advancing anything.
     */
    public function peek(string $entity): int
    {
        $this->guardEntity($entity);

        $sequence = Sequence::query()
            ->where('node_id', $this->identity->id())
            ->where('entity', $entity)
            ->first();

        return $sequence instanceof Sequence ? (int) $sequence->next_value : 1;
    }

    protected function guardEntity(string $entity): void
    {
        if (! in_array($entity, Sequence::entities(), true)) {
            throw new InvalidArgumentException("Unknown sequence entity [{$entity}].");
        }
    }
}
