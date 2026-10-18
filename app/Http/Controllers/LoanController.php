<?php

namespace App\Http\Controllers;

use App\Domain\Access\Permission;
use App\Domain\Loans\DeductionSelection;
use App\Domain\Loans\LoanDraftService;
use App\Domain\Loans\LoanPostService;
use App\Domain\Payments\LoanBalanceCalculator;
use App\Http\Requests\Loans\PreviewLoanRequest;
use App\Http\Requests\Loans\StoreLoanRequest;
use App\Models\Area;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoanController extends Controller
{
    public function index(): Response
    {
        $this->authorize(Permission::LOANS_VIEW);

        $status = $this->statusFilter();
        $search = trim((string) request('search'));

        $loans = Loan::query()
            ->with(['borrower', 'loanProduct', 'area', 'node.area'])
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when(request('area'), fn ($query, $area) => $query->where('area_id', $area))
            ->when($search !== '', fn ($query) => $query->search($search))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Loan $loan): array => [
                'id' => $loan->id,
                'loan_number' => $loan->loan_number,
                'status' => $loan->status,
                'status_label' => $loan->statusLabel(),
                'borrower' => [
                    'id' => $loan->borrower->id,
                    'borrower_number' => $loan->borrower->borrower_number,
                    'full_name' => $loan->borrower->fullName(),
                ],
                'loan_product' => [
                    'id' => $loan->loanProduct->id,
                    'name' => $loan->loanProduct->name,
                    'code' => $loan->loanProduct->code,
                ],
                'area' => $this->loanArea($loan),
                'gross_loan_amount' => (string) $loan->gross_loan_amount,
                'interest_amount' => (string) $loan->interest_amount,
                'deductions_amount' => (string) $loan->deductions_amount,
                'net_proceeds_amount' => (string) $loan->net_proceeds_amount,
                'total_amount_payable' => (string) $loan->total_amount_payable,
                'application_date' => $loan->application_date?->toDateString(),
                'release_date' => $loan->release_date?->toDateString(),
            ]);

        return Inertia::render('loans/Index', [
            'loans' => $loans,
            'filters' => ['search' => $search, 'status' => $status, 'area' => request('area')],
            'areas' => Area::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(fn (Area $area): array => [
                    'id' => $area->id,
                    'code' => $area->code,
                    'name' => $area->name,
                ]),
            'groupedLoansByArea' => $loans->getCollection()->groupBy(fn (array $loan): string => $loan['area']['name'] ?? 'Unassigned')->map(fn ($group): int => $group->count()),
            'statuses' => array_map(
                static fn (string $value, string $label): array => ['value' => $value, 'label' => $label],
                array_keys(Loan::STATUS_LABELS),
                array_values(Loan::STATUS_LABELS),
            ),
        ]);
    }

    public function create(Request $request, LoanDraftService $service): Response
    {
        $this->authorize(Permission::LOANS_CREATE);

        $products = LoanProduct::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'term', 'term_unit', 'payment_frequency', 'exclude_sundays', 'exclude_saturdays', 'release_date_is_day_one', 'interest_method', 'interest_rate']);

        $selectedProduct = $products->firstWhere('id', $request->string('product')) ?? $products->first();
        $amount = $request->string('amount')->toString();
        $interestOverride = $request->string('interest_override_amount')->toString();
        $selection = DeductionSelection::fromInput(
            $request->query('excluded_deduction_codes'),
            $request->query('custom_deductions'),
        );
        $selectedBorrowerId = $request->string('borrower')->toString() ?: null;
        $preview = $selectedProduct && is_numeric($amount)
            ? $service->preview($selectedProduct, $amount, $interestOverride ?: null, $selection)
            : null;

        $search = $request->string('search')->toString();
        $borrowers = Borrower::query()
            ->when($search !== '', fn ($query) => $query->search($search))
            ->when($selectedBorrowerId !== null, fn ($query) => $query->orWhere('id', $selectedBorrowerId))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(10)
            ->get(['id', 'borrower_number', 'first_name', 'middle_name', 'last_name', 'mobile_number', 'default_area_id'])
            ->map(fn (Borrower $borrower): array => [
                'id' => $borrower->id,
                'borrower_number' => $borrower->borrower_number,
                'full_name' => $borrower->fullName(),
                'mobile_number' => $borrower->mobile_number,
                'default_area_id' => $borrower->default_area_id,
            ]);

        $selectedBorrower = Borrower::query()->find($selectedBorrowerId);
        $defaultAreaId = $selectedBorrower?->default_area_id;

        return Inertia::render('loans/Create', [
            'borrowers' => $borrowers,
            'products' => $products->map(fn (LoanProduct $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'term_label' => $product->termLabel(),
                'interest_rate_label' => $product->interestRateLabel(),
            ]),
            'areas' => Area::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'today' => now()->toDateString(),
            'selectedBorrowerId' => $selectedBorrowerId,
            'defaultAreaId' => $defaultAreaId,
            'search' => $search,
            'selectedProductId' => $selectedProduct?->id,
            'grossLoanAmount' => $amount,
            'interestOverrideAmount' => $interestOverride,
            'excludedDeductionCodes' => $selection->excludedCodes(),
            'customDeductions' => $selection->customDeductions(),
            'preview' => $preview,
        ]);
    }

    public function preview(PreviewLoanRequest $request, LoanDraftService $service): JsonResponse
    {
        $validated = $request->validated();

        $product = LoanProduct::query()->whereKey($validated['loan_product_id'])->firstOrFail();

        return response()->json($service->preview(
            $product,
            (string) $validated['gross_loan_amount'],
            $request->interestOverrideAmount(),
            $request->deductionSelection(),
        ));
    }

    public function store(StoreLoanRequest $request, LoanDraftService $service): RedirectResponse
    {
        $validated = $request->validated();

        $loan = $service->create([
            'borrower_id' => $validated['borrower_id'],
            'loan_product_id' => $validated['loan_product_id'],
            'area_id' => $validated['area_id'] ?? null,
            'gross_loan_amount' => (string) $validated['gross_loan_amount'],
            'application_date' => (string) $validated['application_date'],
            'interest_override_amount' => $request->interestOverrideAmount(),
            'deduction_selection' => $request->deductionSelection(),
        ]);

        $this->success("Loan [{$loan->loan_number}] encoded.");

        return to_route('loans.show', $loan);
    }

    public function show(Loan $loan, LoanBalanceCalculator $balances): Response
    {
        $this->authorize(Permission::LOANS_VIEW);

        $loan->load(['borrower', 'loanProduct', 'amortizations', 'payments.collection']);

        return Inertia::render('loans/Show', [
            'loan' => [
                'id' => $loan->id,
                'loan_number' => $loan->loan_number,
                'status' => $loan->status,
                'application_date' => $loan->application_date?->toDateString(),
                'release_date' => $loan->release_date?->toDateString(),
                'borrower' => [
                    'id' => $loan->borrower->id,
                    'borrower_number' => $loan->borrower->borrower_number,
                    'full_name' => $loan->borrower->fullName(),
                ],
                'loan_product' => [
                    'id' => $loan->loanProduct->id,
                    'name' => $loan->loanProduct->name,
                    'code' => $loan->loanProduct->code,
                ],
                'gross_loan_amount' => (string) $loan->gross_loan_amount,
                'interest_amount' => (string) $loan->interest_amount,
                'deductions_amount' => (string) $loan->deductions_amount,
                'net_proceeds_amount' => (string) $loan->net_proceeds_amount,
                'total_amount_payable' => (string) $loan->total_amount_payable,
                'created_at' => $loan->created_at?->toDateTimeString(),
                'amortization' => $loan->amortizations->map(fn ($installment): array => [
                    'installment_number' => $installment->installment_number,
                    'due_date' => $installment->due_date->toDateString(),
                    'principal_due' => (string) $installment->principal_due,
                    'interest_due' => (string) $installment->interest_due,
                    'total_due' => (string) $installment->total_due,
                    'paid_amount' => (string) $installment->paid_amount,
                    'remaining_amount' => (string) $installment->remaining_amount,
                    'status' => $installment->status,
                ])->all(),
            ],
            'balance' => $balances->summary($loan),
            'payments' => $loan->payments
                ->sortByDesc(fn ($payment) => $payment->payment_date->toDateString())
                ->values()
                ->map(fn ($payment): array => [
                    'id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'payment_date' => $payment->payment_date->toDateString(),
                    'amount' => (string) $payment->amount,
                    'allocated_amount' => (string) $payment->allocated_amount,
                    'unapplied_amount' => (string) $payment->unapplied_amount,
                    'payment_method' => $payment->payment_method,
                    'reference_number' => $payment->reference_number,
                    'status' => $payment->status,
                    'status_label' => $payment->statusLabel(),
                    'reversal_reason' => $payment->reversal_reason,
                ])->all(),
        ]);
    }

    public function post(Request $request, Loan $loan, LoanPostService $service): RedirectResponse
    {
        $this->authorize(Permission::LOANS_CREATE);

        $loan = $service->post($loan);

        $this->success("Loan [{$loan->loan_number}] posted.");

        return to_route('loans.show', $loan);
    }

    public function loanArea(Loan $loan): ?array
    {
        $area = $loan->area ?? $loan->node?->area;

        return $area !== null ? [
            'id' => $area->id,
            'code' => $area->code,
            'name' => $area->name,
        ] : null;
    }

    protected function statusFilter(): ?string
    {
        $status = request('status');

        return is_string($status) && array_key_exists($status, Loan::STATUS_LABELS)
            ? $status
            : null;
    }
}
