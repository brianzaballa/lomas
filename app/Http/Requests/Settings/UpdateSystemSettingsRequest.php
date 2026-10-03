<?php

namespace App\Http\Requests\Settings;

use App\Domain\Access\Permission;
use App\Domain\Settings\SequenceFormatter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSystemSettingsRequest extends FormRequest
{
    /**
     * The format rules live with the formatter, so a format can never be
     * accepted here that the formatter would refuse to render.
     */
    public function __construct(
        private readonly SequenceFormatter $formatter,
    ) {
        parent::__construct();
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SETTINGS_MANAGE) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|Rule|string>>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'business_address' => ['nullable', 'string', 'max:1000'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'currency' => ['required', 'string', 'size:3'],
            'currency_symbol' => ['required', 'string', 'max:8'],
            'loan_number_format' => ['required', 'string', 'max:64'],
            'borrower_number_format' => ['required', 'string', 'max:64'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * The validated settings, with the currency code normalised and blank
     * optional values stored as null.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $settings = array_map(
            static fn (mixed $value): mixed => is_string($value) && trim($value) === '' ? null : $value,
            Arr::except($this->validated(), ['reason']),
        );

        $settings['currency'] = strtoupper((string) $settings['currency']);

        return $settings;
    }

    /**
     * A business number must carry the node code and a sequence number.
     * Without the node code two offline nodes would issue the same number, and
     * without a sequence every record would share one number. See AGENTS.md
     * sections 41 and 69.3.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (['loan_number_format', 'borrower_number_format'] as $key) {
                $format = $this->input($key);

                if (! is_string($format)) {
                    continue;
                }

                if (! str_contains($format, '{node}')) {
                    $validator->errors()->add($key, 'The number format must contain the {node} placeholder.');
                }

                if (! $this->formatter->supports($format)) {
                    $validator->errors()->add($key, 'The number format must contain the {seq} placeholder.');
                }
            }
        });
    }
}
