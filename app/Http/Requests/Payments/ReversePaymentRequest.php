<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The reason a posted payment is being reversed, as required for the audit trail.
 */
class ReversePaymentRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
