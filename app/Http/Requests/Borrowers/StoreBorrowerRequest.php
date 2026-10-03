<?php

namespace App\Http\Requests\Borrowers;

use App\Concerns\HandlesBorrowerRules;
use App\Domain\Access\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StoreBorrowerRequest extends FormRequest
{
    use HandlesBorrowerRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::BORROWERS_CREATE) ?? false;
    }

    /**
     * The business number is assigned by the server, never by the encoder.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->borrowerRules();
    }
}
