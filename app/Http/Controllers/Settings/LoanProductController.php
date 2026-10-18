<?php

namespace App\Http\Controllers\Settings;

use App\Actions\LoanProducts\CreateLoanProduct;
use App\Actions\LoanProducts\UpdateLoanProduct;
use App\Domain\Settings\SystemSettings;
use App\Enums\DeductionCalculationMethod;
use App\Enums\InterestMethod;
use App\Enums\PaymentFrequency;
use App\Enums\TermUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreLoanProductRequest;
use App\Http\Requests\Settings\UpdateLoanProductRequest;
use App\Models\LoanDeductionType;
use App\Models\LoanProduct;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Loan product configuration.
 *
 * A product holds the term, the collection calendar and the interest rate. The
 * loan engine reads these values, so nothing about a product is hard coded. A
 * change here applies to loans encoded afterwards; released loans keep the
 * amounts and schedule they already have.
 */
class LoanProductController extends Controller
{
    public function __construct(private readonly SystemSettings $settings) {}

    /**
     * The list of loan products.
     */
    public function index(): Response
    {
        $products = LoanProduct::query()
            ->orderBy('name')
            ->get()
            ->map(fn (LoanProduct $product): array => $this->row($product));

        return Inertia::render('settings/LoanProducts', [
            'products' => $products,
            'termUnits' => $this->options(TermUnit::cases()),
            'paymentFrequencies' => $this->options(PaymentFrequency::cases()),
            'interestMethods' => $this->options(InterestMethod::cases()),
            'deductionTypes' => $this->deductionTypeOptions(),
        ]);
    }

    /**
     * Store a new loan product.
     */
    public function store(StoreLoanProductRequest $request, CreateLoanProduct $createLoanProduct): RedirectResponse
    {
        $product = $createLoanProduct->handle(
            $request->loanProductAttributes(),
            $request->loanProductDeductionTypeIds(),
        );

        $this->success("Loan product [{$product->name}] created.");

        return to_route('loan-products.index');
    }

    /**
     * Update an existing loan product.
     */
    public function update(UpdateLoanProductRequest $request, LoanProduct $loanProduct, UpdateLoanProduct $updateLoanProduct): RedirectResponse
    {
        $updateLoanProduct->handle(
            $loanProduct,
            $request->loanProductAttributes(),
            $request->loanProductDeductionTypeIds(),
        );

        $this->success("Loan product [{$loanProduct->name}] updated.");

        return to_route('loan-products.index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(LoanProduct $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'description' => $product->description,
            'term' => $product->term,
            'term_unit' => $product->term_unit->value,
            'term_unit_label' => $product->term_unit->label(),
            'payment_frequency' => $product->payment_frequency->value,
            'payment_frequency_label' => $product->payment_frequency->label(),
            'exclude_sundays' => $product->exclude_sundays,
            'exclude_saturdays' => $product->exclude_saturdays,
            'release_date_is_day_one' => $product->release_date_is_day_one,
            'interest_method' => $product->interest_method->value,
            'interest_rate' => $product->interest_rate,
            'deduction_type_ids' => array_values(array_map(
                static fn (LoanDeductionType $type): string => $type->id,
                $product->deductionTypes->sortBy('pivot.sort_order')->all(),
            )),
            'deduction_labels' => array_values(array_map(
                static fn (LoanDeductionType $type): string => "{$type->name} ({$type->code})",
                $product->deductionTypes->sortBy('pivot.sort_order')->all(),
            )),
            'is_active' => $product->is_active,
        ];
    }

    /**
     * Every deduction type a product may charge, so the form can offer them.
     *
     * @return list<array{id: string, label: string, method: string, value_label: string, is_active: bool}>
     */
    protected function deductionTypeOptions(): array
    {
        $types = LoanDeductionType::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->all();

        return array_values(array_map(fn (LoanDeductionType $type): array => [
            'id' => $type->id,
            'label' => $type->name,
            'method' => $type->calculation_method->label(),
            'value_label' => $this->deductionValueLabel($type),
            'is_active' => $type->is_active,
        ], $types));
    }

    /**
     * How a deduction type charges, in the words staff use: "1%" or "₱50.00".
     */
    protected function deductionValueLabel(LoanDeductionType $type): string
    {
        if ($type->calculation_method === DeductionCalculationMethod::FixedAmount) {
            return $type->fixed_amount === null
                ? ''
                : $this->settings->formatMoney(Money::of($type->fixed_amount));
        }

        return $type->rateLabel();
    }

    /**
     * @param  list<TermUnit|PaymentFrequency|InterestMethod>  $cases
     * @return list<array{value: string, label: string}>
     */
    protected function options(array $cases): array
    {
        return array_map(
            static fn (TermUnit|PaymentFrequency|InterestMethod $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            $cases,
        );
    }
}
