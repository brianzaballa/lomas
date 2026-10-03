<?php

use App\Domain\Nodes\NodeIdentity;
use App\Domain\Nodes\SequenceAllocator;
use App\Models\Sequence;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->allocator = app(SequenceAllocator::class);
});

it('hands out one number at a time starting at one', function () {
    expect($this->allocator->next(Sequence::ENTITY_BORROWER))->toBe(1)
        ->and($this->allocator->next(Sequence::ENTITY_BORROWER))->toBe(2)
        ->and($this->allocator->next(Sequence::ENTITY_BORROWER))->toBe(3);
});

it('never hands out the same number twice under concurrency', function () {
    $numbers = [];

    for ($i = 0; $i < 25; $i++) {
        $numbers[] = $this->allocator->next(Sequence::ENTITY_LOAN);
    }

    expect($numbers)->toBe(range(1, 25))
        ->and(array_unique($numbers))->toHaveCount(25);
});

it('keeps a separate counter per entity', function () {
    expect($this->allocator->next(Sequence::ENTITY_BORROWER))->toBe(1)
        ->and($this->allocator->next(Sequence::ENTITY_LOAN))->toBe(1)
        ->and($this->allocator->next(Sequence::ENTITY_LOAN))->toBe(2)
        ->and($this->allocator->next(Sequence::ENTITY_PAYMENT))->toBe(1)
        ->and($this->allocator->next(Sequence::ENTITY_BORROWER))->toBe(2);
});

it('reads the next value without advancing the counter', function () {
    expect($this->allocator->peek(Sequence::ENTITY_BORROWER))->toBe(1)
        ->and($this->allocator->peek(Sequence::ENTITY_BORROWER))->toBe(1)
        ->and($this->allocator->next(Sequence::ENTITY_BORROWER))->toBe(1)
        ->and($this->allocator->peek(Sequence::ENTITY_BORROWER))->toBe(2);
});

it('keeps the counters of two nodes apart so both can work offline', function () {
    $local = app(NodeIdentity::class)->id();

    // A node counter row that arrived from another node, e.g. through a bundle.
    Sequence::query()->create([
        'node_id' => (string) Str::uuid7(),
        'entity' => Sequence::ENTITY_BORROWER,
        'next_value' => 500,
    ]);

    expect($this->allocator->next(Sequence::ENTITY_BORROWER))->toBe(1)
        ->and(Sequence::query()->where('node_id', $local)->where('entity', Sequence::ENTITY_BORROWER)->firstOrFail()->next_value)
        ->toBe(2)
        ->and(Sequence::query()->count())->toBe(2);
});

it('refuses an entity that has no counter', function () {
    $this->allocator->next('collector');
})->throws(InvalidArgumentException::class);
