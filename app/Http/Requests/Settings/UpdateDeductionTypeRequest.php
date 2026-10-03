<?php

namespace App\Http\Requests\Settings;

use App\Concerns\HandlesDeductionTypeRules;
use App\Domain\Access\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeductionTypeRequest extends FormRequest
{
    use HandlesDeductionTypeRules;

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
            ...$this->deductionTypeRules(),
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('loan_deduction_types', 'code')->ignore($this->route('deductionType')),
            ],
        ];
    }
}
