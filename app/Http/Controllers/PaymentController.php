<?php

namespace App\Http\Controllers;

use App\Domain\Access\Permission;
use App\Domain\Payments\PaymentReversalService;
use App\Http\Requests\Payments\ReversePaymentRequest;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    /**
     * Every payment recorded on this node, newest first.
     */
    public function index(Request $request): Response
    {
        $this->authorize(Permission::PAYMENTS_VIEW);

        $status = $this->statusFilter($request);
        $search = trim($request->string('search')->toString());
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        $payments = Payment::query()
            ->with(['loan.borrower', 'collection'])
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->search($search))
            ->when($from !== '', fn ($query) => $query->whereDate('payment_date', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('payment_date', '<=', $to))
            ->orderByDesc('payment_date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Payment $payment): array => [
                'id' => $payment->getKey(),
                'payment_number' => $payment->payment_number,
                'payment_date' => $payment->payment_date->toDateString(),
                'loan_id' => $payment->loan_id,
                'loan_number' => $payment->loan->loan_number,
                'borrower_name' => $payment->loan->borrower->fullName(),
                'amount' => (string) $payment->amount,
                'allocated_amount' => (string) $payment->allocated_amount,
                'unapplied_amount' => (string) $payment->unapplied_amount,
                'payment_method' => $payment->payment_method,
                'reference_number' => $payment->reference_number,
                'status' => $payment->status,
                'status_label' => $payment->statusLabel(),
                'reversal_reason' => $payment->reversal_reason,
            ]);

        return Inertia::render('payments/Index', [
            'payments' => $payments,
            'filters' => ['search' => $search, 'status' => $status, 'from' => $from, 'to' => $to],
            'statuses' => array_map(
                static fn (string $value, string $label): array => ['value' => $value, 'label' => $label],
                array_keys(Payment::STATUS_LABELS),
                array_values(Payment::STATUS_LABELS),
            ),
        ]);
    }

    /**
     * Reverse a posted payment, keeping the row and the audit trail.
     */
    public function reverse(ReversePaymentRequest $request, Payment $payment, PaymentReversalService $service): RedirectResponse
    {
        $this->authorize(Permission::PAYMENTS_REVERSE);

        $service->reverse($payment, (string) $request->validated('reason'));

        $this->success("Payment [{$payment->payment_number}] reversed.");

        return back();
    }

    private function statusFilter(Request $request): ?string
    {
        $status = $request->string('status')->toString();

        return array_key_exists($status, Payment::STATUS_LABELS) ? $status : null;
    }
}
