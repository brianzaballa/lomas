<?php

namespace App\Http\Requests\Settings;

use App\Concerns\HandlesDeductionTypeRules;
use App\Domain\Access\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StoreDeductionTypeRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->deductionTypeRules();
    }
}
