<?php

namespace App\Domain\Payments;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Domain\Nodes\NodeIdentity;
use App\Domain\Reporting\NcrPostingService;
use App\Models\Collection;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Prepares and posts a collection day.
 *
 * A day is prepared by adding payments to an open collection, and it is posted
 * as a unit: posting allocates every staged payment against the loan's oldest
 * unpaid installments and closes the day. After that nothing on the day may
 * change except by a reversal. See AGENTS.md sections 63.1.5, 63.1.7 and 63.1.8.
 */
class CollectionPostingService
{
    /**
     * A loan must have a frozen schedule before anything can be collected on it.
     *
     * @var list<string>
     */
    protected const COLLECTABLE_STATUSES = [
        Loan::STATUS_ACTIVE,
    ];

    public function __construct(
        private readonly PaymentAllocator $allocator,
        private readonly LoanBalanceCalculator $balances,
        private readonly PaymentNumberGenerator $numbers,
        private readonly NodeIdentity $nodes,
        private readonly AuditLogger $audit,
        private readonly NcrPostingService $ncrPostings,
    ) {}

    /**
     * Open a collection day, or return the existing one.
     *
     * Preparing a day that has already passed is allowed; its business_date is
     * kept as the day the money was collected.
     */
    public function open(CarbonInterface $businessDate): Collection
    {
        $date = $businessDate->toDateString();

        $collection = Collection::query()
            ->where('node_id', $this->nodes->id())
            ->whereDate('business_date', $date)
            ->first();

        if ($collection instanceof Collection) {
            return $collection;
        }

        return Collection::query()->create([
            'node_id' => $this->nodes->id(),
            'business_date' => $date,
            'status' => Collection::STATUS_OPEN,
            'prepared_by' => Auth::id(),
            'prepared_at' => now(),
        ]);
    }

    /**
     * Stage a payment on an open collection day.
     *
     * The payment is pending until the day is posted, so it does not touch any
     * balance while the day is still being prepared.
     */
    public function addPayment(
        Collection $collection,
        Loan $loan,
        string $amount,
        ?string $paymentMethod = null,
        ?string $referenceNumber = null,
    ): Payment {
        $this->guardOpen($collection);

        if (! in_array($loan->status, self::COLLECTABLE_STATUSES, true)) {
            throw new LogicException('Payments can only be collected on a released loan.');
        }

        $money = Money::of($amount);

        if (! $money->isPositive()) {
            throw new LogicException('A payment amount must be greater than zero.');
        }

        return Payment::query()->create([
            'node_id' => $collection->node_id,
            'payment_number' => $this->numbers->next(),
            'loan_id' => $loan->getKey(),
            'collection_id' => $collection->getKey(),
            'payment_date' => $collection->business_date,
            'amount' => $money->toDecimalString(),
            'status' => Payment::STATUS_PENDING,
            'payment_method' => $paymentMethod ?? Payment::METHOD_CASH,
            'reference_number' => $referenceNumber,
            'received_by' => Auth::id(),
        ]);
    }

    /**
     * Reconcile the staged payments of an open day with the amounts entered.
     *
     * The daily form always lists every collectable loan, so this is idempotent:
     * a loan with an amount is created or updated, and a loan whose amount was
     * cleared has its staged payment removed. Nothing posted is touched.
     *
     * @param  list<array{loan_id: string, amount: string|null, payment_method?: string|null, reference_number?: string|null}>  $entries
     */
    public function stage(Collection $collection, array $entries): void
    {
        $this->guardOpen($collection);

        DB::transaction(function () use ($collection, $entries): void {
            $staged = $collection->payments()
                ->where('status', Payment::STATUS_PENDING)
                ->get()
                ->keyBy('loan_id');

            $kept = [];

            foreach ($entries as $entry) {
                $amount = trim((string) ($entry['amount'] ?? ''));

                if ($amount === '' || ! Money::of($amount)->isPositive()) {
                    continue;
                }

                $kept[] = $entry['loan_id'];

                $existing = $staged->get($entry['loan_id']);

                if ($existing instanceof Payment) {
                    $existing->forceFill([
                        'amount' => Money::of($amount)->toDecimalString(),
                        'payment_method' => $entry['payment_method'] ?? $existing->payment_method,
                        'reference_number' => $entry['reference_number'] ?? $existing->reference_number,
                    ])->save();

                    continue;
                }

                $loan = Loan::query()->whereKey($entry['loan_id'])->firstOrFail();

                $this->addPayment(
                    $collection,
                    $loan,
                    $amount,
                    $entry['payment_method'] ?? null,
                    $entry['reference_number'] ?? null,
                );
            }

            $removed = $collection->payments()
                ->where('status', Payment::STATUS_PENDING)
                ->whereNotIn('loan_id', $kept)
                ->get();

            foreach ($removed as $payment) {
                $payment->delete();
            }
        });
    }

    /**
     * Remove a payment that is still staged on an open collection day.
     *
     * Only a pending payment may be removed. A posted payment is a transaction
     * and can only be corrected by a reversal.
     */
    public function removePayment(Collection $collection, Payment $payment): void
    {
        $this->guardOpen($collection);

        if ($payment->collection_id !== $collection->getKey() || ! $payment->isPending()) {
            throw new LogicException('Only a pending payment on this collection day can be removed.');
        }

        $payment->delete();
    }

    /**
     * Post the day as a unit.
     *
     * Every staged payment is allocated against the loan's oldest unpaid
     * installment and becomes a posted transaction; the day is then closed.
     */
    public function post(Collection $collection): Collection
    {
        $this->guardOpen($collection);

        return DB::transaction(function () use ($collection): Collection {
            $collection = Collection::query()->whereKey($collection->getKey())->lockForUpdate()->firstOrFail();

            if (! $collection->isOpen()) {
                throw new LogicException('This collection day has already been posted.');
            }

            $touchedLoans = [];

            foreach ($collection->payments()->where('status', Payment::STATUS_PENDING)->get() as $payment) {
                $this->postPayment($payment);
                $touchedLoans[$payment->loan_id] = true;
            }

            foreach (array_keys($touchedLoans) as $loanId) {
                $loan = Loan::query()->whereKey($loanId)->first();

                if ($loan instanceof Loan) {
                    $this->balances->syncAmortizations($loan);
                }
            }

            $collection->forceFill([
                'status' => Collection::STATUS_POSTED,
                'posted_by' => Auth::id(),
                'posted_at' => now(),
            ])->save();

            $collection->refreshTotals();

            $this->audit->recordCreated(
                AuditAction::COLLECTION_POSTED,
                $collection,
                $collection->only(['business_date', 'payment_count', 'total_amount']),
            );

            return $collection->refresh();
        });
    }

    /**
     * Allocate one staged payment and mark it posted.
     */
    private function postPayment(Payment $payment): void
    {
        $loan = Loan::query()->whereKey($payment->loan_id)->firstOrFail();

        $result = $this->allocator->allocate(
            Money::of($payment->amount),
            $this->balances->openInstallments($loan),
        );

        foreach ($result['allocations'] as $allocation) {
            PaymentAllocation::query()->create([
                'node_id' => $payment->node_id,
                'payment_id' => $payment->getKey(),
                'loan_amortization_id' => $allocation['loan_amortization_id'],
                'component' => $allocation['component'],
                'amount' => $allocation['amount'],
            ]);
        }

        $payment->forceFill([
            'status' => Payment::STATUS_POSTED,
            'allocated_amount' => $result['allocated'],
            'unapplied_amount' => $result['unapplied'],
        ])->save();

        $this->audit->recordCreated(AuditAction::PAYMENT_POSTED, $payment, $payment->only([
            'payment_number',
            'loan_id',
            'payment_date',
            'amount',
            'allocated_amount',
            'unapplied_amount',
        ]));
    }

    private function guardOpen(Collection $collection): void
    {
        if (! $collection->isOpen()) {
            throw new LogicException('This collection day has already been posted.');
        }

        // A posted Notes Collection Report period soft-locks its payments:
        // nothing on a day inside it may be staged, posted or removed until
        // the period is unposted.
        $this->ncrPostings->assertUnlocked($collection->business_date);
    }
}
