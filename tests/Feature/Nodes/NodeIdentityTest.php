<?php

use App\Domain\Nodes\NodeIdentity;
use App\Domain\Nodes\SequenceAllocator;
use App\Models\Borrower;
use App\Models\Node;
use App\Models\Sequence;
use Illuminate\Support\Str;

it('registers the node from configuration on first use', function () {
    config()->set('lomas.node.code', 'CAV1');
    config()->set('lomas.node.name', 'Cavite Node');
    config()->set('lomas.node.role', Node::ROLE_CENTRAL);

    $node = app(NodeIdentity::class)->node();

    expect($node->code)->toBe('CAV1')
        ->and($node->name)->toBe('Cavite Node')
        ->and($node->role)->toBe(Node::ROLE_CENTRAL)
        ->and($node->isCentral())->toBeTrue()
        ->and(Str::isUuid($node->id))->toBeTrue()
        ->and(Node::query()->count())->toBe(1);
});

it('keeps the same node identity when it is read again', function () {
    $identity = app(NodeIdentity::class);

    $first = $identity->id();

    expect($identity->id())->toBe($first)
        ->and($identity->node()->is($identity->node()))->toBeTrue()
        ->and(Node::query()->count())->toBe(1);
});

it('rejects a node code that cannot appear in a business number', function () {
    config()->set('lomas.node.code', 'manila one');

    app(NodeIdentity::class)->code();
})->throws(InvalidArgumentException::class);

it('rejects a node code that is too short', function () {
    config()->set('lomas.node.code', 'M');

    app(NodeIdentity::class)->code();
})->throws(InvalidArgumentException::class);

it('refuses to act as a second node when the identity has already been issued', function () {
    $original = app(NodeIdentity::class)->id();

    config()->set('lomas.node.code', 'CAV1');

    expect(fn () => app(NodeIdentity::class)->node())->toThrow(LogicException::class)
        ->and(Node::query()->count())->toBe(1)
        ->and(Node::query()->first()->id)->toBe($original);
});

it('treats an unknown role as a branch rather than central', function () {
    config()->set('lomas.node.role', 'head-office');

    $identity = app(NodeIdentity::class);

    expect($identity->role())->toBe(Node::ROLE_BRANCH)
        ->and($identity->isCentral())->toBeFalse();
});

it('stamps the local node on every borrower it writes', function () {
    $nodeId = app(NodeIdentity::class)->id();

    $borrower = Borrower::factory()->create();

    expect($borrower->node_id)->toBe($nodeId)
        ->and(Str::isUuid($borrower->id))->toBeTrue();
});

it('keeps an explicit node when a record is written on behalf of another node', function () {
    $foreign = (string) Str::uuid7();

    $borrower = Borrower::factory()->create(['node_id' => $foreign]);

    expect($borrower->node_id)->toBe($foreign);
});

it('gives the local node its own number sequence', function () {
    $nodeId = app(NodeIdentity::class)->id();

    expect(app(SequenceAllocator::class)->next(Sequence::ENTITY_BORROWER))->toBe(1);

    $sequence = Sequence::query()
        ->where('node_id', $nodeId)
        ->where('entity', Sequence::ENTITY_BORROWER)
        ->firstOrFail();

    expect($sequence->next_value)->toBe(2);
});
