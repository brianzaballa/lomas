<?php

use App\Domain\Settings\SequenceFormatter;

beforeEach(function () {
    $this->formatter = new SequenceFormatter;
});

it('renders loan numbers with the node, the year and a padded sequence', function () {
    expect($this->formatter->format('LN-{node}-{year}-{seq:6}', 42, now()->setDate(2026, 10, 3), 'MNL1'))
        ->toBe('LN-MNL1-2026-000042');
});

it('renders borrower numbers', function () {
    expect($this->formatter->format('BR-{node}-{seq:6}', 7, now(), 'MNL1'))->toBe('BR-MNL1-000007');
});

it('renders the node code of another node so two offline nodes never collide', function () {
    $first = $this->formatter->format('BR-{node}-{seq:6}', 1, now(), 'MNL1');
    $second = $this->formatter->format('BR-{node}-{seq:6}', 1, now(), 'CAV1');

    expect($first)->toBe('BR-MNL1-000001')
        ->and($second)->toBe('BR-CAV1-000001')
        ->and($first)->not->toBe($second);
});

it('renders the month when the format asks for it', function () {
    expect($this->formatter->format('LN-{node}-{year}{month}-{seq:3}', 5, now()->setDate(2026, 9, 30), 'MNL1'))
        ->toBe('LN-MNL1-202609-005');
});

it('renders a sequence without padding', function () {
    expect($this->formatter->format('LN-{node}-{seq}', 12, now(), 'MNL1'))->toBe('LN-MNL1-12');
});

it('never renders a negative sequence', function () {
    expect($this->formatter->format('BR-{node}-{seq:4}', -3, now(), 'MNL1'))->toBe('BR-MNL1-0000');
});

it('rejects unknown tokens', function () {
    $this->formatter->format('LN-{week}', 1, now(), 'MNL1');
})->throws(InvalidArgumentException::class);

it('accepts a business number format that carries the node and a sequence', function () {
    expect($this->formatter->supports('LN-{node}-{year}-{seq:6}'))->toBeTrue()
        ->and($this->formatter->supports('BR-{node}-{seq:6}'))->toBeTrue()
        ->and($this->formatter->supports('BR-{node}-{seq}'))->toBeTrue();
});

it('rejects a format without the node code because two offline nodes would collide', function () {
    expect($this->formatter->supports('BR-{seq:6}'))->toBeFalse()
        ->and($this->formatter->supports('LN-{year}-{seq:6}'))->toBeFalse();
});

it('rejects a format without a sequence because every record would share one number', function () {
    expect($this->formatter->supports('BR-{node}'))->toBeFalse()
        ->and($this->formatter->supports('NO-PLACEHOLDER'))->toBeFalse();
});
