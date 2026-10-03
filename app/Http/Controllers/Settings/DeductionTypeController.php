<?php

namespace App\Http\Controllers\Settings;

use App\Actions\DeductionTypes\CreateDeductionType;
use App\Actions\DeductionTypes\UpdateDeductionType;
use App\Domain\Settings\SystemSettings;
use App\Enums\DeductionCalculationMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreDeductionTypeRequest;
use App\Http\Requests\Settings\UpdateDeductionTypeRequest;
use App\Models\LoanDeductionType;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Deduction type configuration.
 *
 * A type describes how a deduction is calculated, e.g. a percentage of the
 * loan amount or a fixed amount. It is not a ledger: the amount a loan was
 * actually charged is copied into the loan at release, so changing a type
 * never changes an existing loan.
 *
 * Interest is not a deduction type. Its rate belongs to the loan product.
 */
class DeductionTypeController extends Controller
{
    public function __construct(private readonly SystemSettings $settings) {}

    /**
     * The list of deduction types.
     */
    public function index(): Response
    {
        $types = LoanDeductionType::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (LoanDeductionType $type): array => $this->row($type));

        return Inertia::render('settings/DeductionTypes', [
            'types' => $types,
            'calculationMethods' => array_map(
                static fn (DeductionCalculationMethod $method): array => [
                    'value' => $method->value,
                    'label' => $method->label(),
                ],
                DeductionCalculationMethod::cases(),
            ),
            'currency_symbol' => $this->settings->currencySymbol(),
        ]);
    }

    /**
     * Store a new deduction type.
     */
    public function store(StoreDeductionTypeRequest $request, CreateDeductionType $createDeductionType): RedirectResponse
    {
        $type = $createDeductionType->handle($request->deductionTypeAttributes());

        $this->success("Deduction type [{$type->name}] created.");

        return to_route('deduction-types.index');
    }

    /**
     * Update an existing deduction type.
     */
    public function update(UpdateDeductionTypeRequest $request, LoanDeductionType $deductionType, UpdateDeductionType $updateDeductionType): RedirectResponse
    {
        $updateDeductionType->handle($deductionType, $request->deductionTypeAttributes());

        $this->success("Deduction type [{$deductionType->name}] updated.");

        return to_route('deduction-types.index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(LoanDeductionType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'code' => $type->code,
            'description' => $type->description,
            'calculation_method' => $type->calculation_method->value,
            'calculation_method_label' => $type->calculation_method->label(),
            'rate' => $type->rate,
            'rate_label' => $type->rateLabel(),
            'fixed_amount' => $type->fixed_amount,
            'fixed_amount_label' => $type->fixed_amount === null
                ? null
                : $this->settings->formatMoney(Money::of($type->fixed_amount)),
            'is_active' => $type->is_active,
            'sort_order' => $type->sort_order,
        ];
    }
}
