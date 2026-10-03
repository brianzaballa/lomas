<?php

namespace App\Http\Requests\Settings;

use App\Concerns\HandlesLoanProductRules;
use App\Domain\Access\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StoreLoanProductRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->loanProductRules();
    }
}
