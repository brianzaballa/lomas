<?php

use App\Domain\Payments\PaymentAllocator;
use App\Models\PaymentAllocation;
use App\Support\Money;

function installmentsOf(string $principalEach, int $count): array
{
    $installments = [];

    for ($index = 1; $index <= $count; $index++) {
        $installments[] = [
            'loan_amortization_id' => "installment-{$index}",
            'interest_remaining' => '0.00',
            'principal_remaining' => $principalEach,
        ];
    }

    return $installments;
}

it('settles the oldest installments first', function () {
    $result = (new PaymentAllocator)->allocate(Money::of('150.00'), installmentsOf('100.00', 3));

    expect($result['allocated'])->toBe('150.00')
        ->and($result['unapplied'])->toBe('0.00')
        ->and($result['allocations'])->toBe([
            ['loan_amortization_id' => 'installment-1', 'component' => PaymentAllocation::COMPONENT_PRINCIPAL, 'amount' => '100.00'],
            ['loan_amortization_id' => 'installment-2', 'component' => PaymentAllocation::COMPONENT_PRINCIPAL, 'amount' => '50.00'],
        ]);
});

it('leaves the excess unapplied instead of pushing it forward', function () {
    $result = (new PaymentAllocator)->allocate(Money::of('250.00'), installmentsOf('100.00', 2));

    expect($result['allocated'])->toBe('200.00')
        ->and($result['unapplied'])->toBe('50.00')
        ->and($result['allocations'])->toHaveCount(2);
});

it('allocates a partial payment against a single installment', function () {
    $result = (new PaymentAllocator)->allocate(Money::of('50.00'), installmentsOf('100.00', 2));

    expect($result['allocated'])->toBe('50.00')
        ->and($result['unapplied'])->toBe('0.00')
        ->and($result['allocations'])->toBe([
            ['loan_amortization_id' => 'installment-1', 'component' => PaymentAllocation::COMPONENT_PRINCIPAL, 'amount' => '50.00'],
        ]);
});

it('covers interest before principal within an installment', function () {
    $installments = [[
        'loan_amortization_id' => 'installment-1',
        'interest_remaining' => '20.00',
        'principal_remaining' => '100.00',
    ]];

    $result = (new PaymentAllocator)->allocate(Money::of('100.00'), $installments);

    expect($result['allocations'])->toBe([
        ['loan_amortization_id' => 'installment-1', 'component' => PaymentAllocation::COMPONENT_INTEREST, 'amount' => '20.00'],
        ['loan_amortization_id' => 'installment-1', 'component' => PaymentAllocation::COMPONENT_PRINCIPAL, 'amount' => '80.00'],
    ]);
});

it('allocates nothing when there is nothing owed', function () {
    $result = (new PaymentAllocator)->allocate(Money::of('100.00'), []);

    expect($result['allocations'])->toBe([])
        ->and($result['allocated'])->toBe('0.00')
        ->and($result['unapplied'])->toBe('100.00');
});
