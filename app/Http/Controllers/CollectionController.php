<?php

namespace App\Http\Controllers;

use App\Domain\Access\Permission;
use App\Domain\Nodes\NodeIdentity;
use App\Domain\Payments\CollectionPostingService;
use App\Domain\Payments\DailyCollectionSchedule;
use App\Http\Requests\Payments\StoreCollectionRequest;
use App\Models\Area;
use App\Models\Collection;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    public function __construct(
        private readonly CollectionPostingService $posting,
        private readonly DailyCollectionSchedule $schedule,
        private readonly NodeIdentity $nodes,
    ) {}

    /**
     * The daily collection screen for one business date.
     *
     * It shows the loans due on or before the date, the amounts already staged
     * in the open collection, and the payments once the day is posted. It is
     * filtered by area and borrower name so a collector can work through a
     * portion of the book at a time. See AGENTS.md sections 27, 28 and 63.1.13.
     */
    public function daily(Request $request): Response
    {
        $this->authorize(Permission::PAYMENTS_VIEW);

        $date = $this->resolveDate($request->string('date')->toString());
        $area = $this->nullableString($request->string('area')->toString());
        $name = $this->nullableString($request->string('name')->toString());

        $collection = Collection::query()
            ->where('node_id', $this->nodes->id())
            ->whereDate('business_date', $date->toDateString())
            ->first();

        $payments = $collection instanceof Collection
            ? $collection->payments()->with(['loan.borrower'])->get()
            : collect();

        $staged = $payments
            ->where('status', Payment::STATUS_PENDING)
            ->keyBy('loan_id');

        $scheduled = array_map(function (array $row) use ($staged): array {
            $pending = $staged->get($row['loan_id']);

            $row['staged_amount'] = $pending instanceof Payment ? (string) $pending->amount : '';
            $row['staged_payment_id'] = $pending instanceof Payment ? $pending->getKey() : null;

            return $row;
        }, $this->schedule->forDate($date, $area, $name));

        return Inertia::render('collections/Daily', [
            'date' => $date->toDateString(),
            'today' => now()->toDateString(),
            'collection' => $collection instanceof Collection ? [
                'id' => $collection->getKey(),
                'business_date' => $collection->business_date->toDateString(),
                'status' => $collection->status,
                'is_open' => $collection->isOpen(),
                'payment_count' => $collection->payment_count,
                'total_amount' => (string) $collection->total_amount,
                'prepared_at' => $collection->prepared_at?->toDateTimeString(),
                'posted_at' => $collection->posted_at?->toDateTimeString(),
            ] : null,
            'scheduled' => $scheduled,
            'filters' => [
                'area' => $area,
                'name' => $name,
            ],
            'areas' => Area::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Area $area): array => ['id' => $area->getKey(), 'name' => $area->name])
                ->values()
                ->all(),
            'payments' => $payments->map(fn (Payment $payment): array => [
                'id' => $payment->getKey(),
                'payment_number' => $payment->payment_number,
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
            ])->values()->all(),
            'methods' => Payment::methods(),
        ]);
    }

    /**
     * Reconcile the staged amounts of an open collection day.
     */
    public function stage(StoreCollectionRequest $request): RedirectResponse
    {
        $this->authorize(Permission::PAYMENTS_CREATE);

        $date = Carbon::parse((string) $request->validated('business_date'));

        $collection = $this->posting->open($date);

        $this->posting->stage($collection, $request->entries());

        $this->success('Collection saved. Review the amounts and post the day.');

        return to_route('collections.daily', ['date' => $date->toDateString()]);
    }

    /**
     * Post an open collection day as a unit.
     */
    public function post(Request $request): RedirectResponse
    {
        $this->authorize(Permission::PAYMENTS_CREATE);

        $date = $this->resolveDate($request->string('date')->toString());

        $collection = Collection::query()
            ->where('node_id', $this->nodes->id())
            ->whereDate('business_date', $date->toDateString())
            ->firstOrFail();

        $this->posting->post($collection);

        $this->success("Collection for {$date->toDateString()} posted.");

        return to_route('collections.daily', ['date' => $date->toDateString()]);
    }

    /**
     * Remove a payment that is still staged on an open day.
     */
    public function destroyPayment(Payment $payment): RedirectResponse
    {
        $this->authorize(Permission::PAYMENTS_CREATE);

        $collection = $payment->collection;

        if (! $collection instanceof Collection) {
            abort(404);
        }

        $this->posting->removePayment($collection, $payment);

        $this->success('Staged payment removed.');

        return to_route('collections.daily', ['date' => $collection->business_date->toDateString()]);
    }

    private function resolveDate(string $date): Carbon
    {
        return $date !== '' ? Carbon::parse($date) : Carbon::today();
    }

    private function nullableString(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
