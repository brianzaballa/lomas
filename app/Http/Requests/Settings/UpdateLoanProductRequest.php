<?php

namespace App\Http\Requests\Settings;

use App\Concerns\HandlesLoanProductRules;
use App\Domain\Access\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoanProductRequest extends FormRequest
{
    use HandlesLoanProductRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SETTINGS_MANAGE) ?? false;
    }

    /**
     * The code stays unique when it is renamed.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->loanProductRules(),
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('loan_products', 'code')->ignore($this->route('loanProduct')),
            ],
        ];
    }
}
