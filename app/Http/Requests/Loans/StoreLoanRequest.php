<?php

namespace App\Http\Requests\Loans;

use App\Concerns\HandlesLoanEncodingRules;
use App\Domain\Access\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanRequest extends FormRequest
{
    use HandlesLoanEncodingRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::LOANS_CREATE) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'borrower_id' => ['required', 'uuid', Rule::exists('borrowers', 'id')],
            'loan_product_id' => ['required', 'uuid', Rule::exists('loan_products', 'id')],
            'gross_loan_amount' => ['required', 'numeric', 'gt:0'],
            'application_date' => ['required', 'date'],
            'area_id' => ['nullable', 'uuid', Rule::exists('areas', 'id')],
            ...$this->loanEncodingRules(),
        ];
    }
}
