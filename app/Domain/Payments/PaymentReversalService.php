<?php

namespace App\Domain\Payments;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Domain\Reporting\NcrPostingService;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Reverses a posted payment without deleting anything.
 *
 * The payment keeps its row, gains a reason, an actor and a timestamp, and its
 * allocations stop counting toward loan balances, collection totals and reports.
 * The replacement, if any, is a new payment that points back at the one it
 * corrects. See AGENTS.md sections 33 and 63.1.8.
 */
class PaymentReversalService
{
    public function __construct(
        private readonly LoanBalanceCalculator $balances,
        private readonly AuditLogger $audit,
        private readonly NcrPostingService $ncrPostings,
    ) {}

    public function reverse(Payment $payment, string $reason): Payment
    {
        $payment->refresh();

        if (! $payment->isPosted()) {
            throw new LogicException('Only a posted payment can be reversed.');
        }

        // A posted Notes Collection Report period soft-locks its payments, so
        // a reversal inside it waits until the period is unposted.
        $this->ncrPostings->assertUnlocked($payment->payment_date);

        return DB::transaction(function () use ($payment, $reason): Payment {
            $payment->forceFill([
                'status' => Payment::STATUS_REVERSED,
                'reversed_at' => now(),
                'reversed_by' => Auth::id(),
                'reversal_reason' => $reason,
            ])->save();

            $this->balances->syncAmortizations($payment->loan);

            $payment->collection?->refreshTotals();

            $this->audit->recordUpdated(
                AuditAction::PAYMENT_REVERSED,
                $payment,
                ['status' => Payment::STATUS_POSTED],
                [
                    'status' => Payment::STATUS_REVERSED,
                    'reversal_reason' => $reason,
                ],
                $reason,
            );

            return $payment->refresh();
        });
    }
}
