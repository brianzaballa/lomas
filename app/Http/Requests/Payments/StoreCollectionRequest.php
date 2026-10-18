<?php

namespace App\Http\Requests\Payments;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The staged amounts entered for one collection day.
 *
 * Every collectable loan is submitted, including the ones left blank, so the
 * server can reconcile the staged payments in one idempotent step.
 */
class StoreCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_date' => ['required', 'date'],
            'entries' => ['present', 'array'],
            'entries.*.loan_id' => ['required', 'uuid'],
            'entries.*.amount' => ['nullable', 'numeric', 'min:0'],
            'entries.*.payment_method' => ['nullable', Rule::in(Payment::methods())],
            'entries.*.reference_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The entries, with the amounts kept as the exact strings that were typed so
     * no value passes through floating point. See AGENTS.md section 19.
     *
     * @return list<array{loan_id: string, amount: string|null, payment_method: string|null, reference_number: string|null}>
     */
    public function entries(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->validated('entries');

        return array_map(static fn (array $row): array => [
            'loan_id' => (string) $row['loan_id'],
            'amount' => isset($row['amount']) ? (string) $row['amount'] : null,
            'payment_method' => isset($row['payment_method']) ? (string) $row['payment_method'] : null,
            'reference_number' => isset($row['reference_number']) ? (string) $row['reference_number'] : null,
        ], $rows);
    }
}
