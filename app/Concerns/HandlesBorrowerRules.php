<?php

namespace App\Concerns;

use App\Enums\BorrowerStatus;
use Illuminate\Validation\Rule;

/**
 * The validation rules shared by the borrower create and update forms.
 *
 * Kept in one place so the encoder can never be given different rules
 * depending on which screen a record is saved from.
 *
 * The mobile number is deliberately not unique. Households share one number,
 * and blocking it would push staff into inventing numbers they do not have.
 */
trait HandlesBorrowerRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function borrowerRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'mobile_number' => ['required', 'string', 'max:20', 'regex:/^[0-9+()\-\s]+$/'],
            'address' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(BorrowerStatus::values())],
            'default_area_id' => ['nullable', 'uuid', 'exists:areas,id'],
        ];
    }

    /**
     * The validated borrower attributes, in the shape the actions expect.
     *
     * A null status means "leave the current status alone", which is what an
     * update that does not touch the status field sends.
     *
     * @return array{
     *     first_name: string,
     *     middle_name: string|null,
     *     last_name: string,
     *     suffix: string|null,
     *     birth_date: string|null,
     *     mobile_number: string,
     *     address: string,
     *     status: string|null
     * }
     */
    public function borrowerAttributes(): array
    {
        return [
            'first_name' => $this->validated('first_name'),
            'middle_name' => $this->validated('middle_name'),
            'last_name' => $this->validated('last_name'),
            'suffix' => $this->validated('suffix'),
            'birth_date' => $this->validated('birth_date'),
            'mobile_number' => $this->validated('mobile_number'),
            'address' => $this->validated('address'),
            'status' => $this->validated('status'),
            'default_area_id' => $this->validated('default_area_id'),
        ];
    }

    /**
     * Trim what was typed and store a cleared optional field as null instead
     * of an empty string, so "no middle name" has exactly one representation.
     */
    public function prepareForValidation(): void
    {
        $cleaned = [];

        foreach (['first_name', 'middle_name', 'last_name', 'suffix', 'birth_date', 'mobile_number', 'address'] as $key) {
            if ($this->exists($key)) {
                $cleaned[$key] = $this->cleanValue($this->input($key));
            }
        }

        if ($cleaned !== []) {
            $this->merge($cleaned);
        }
    }

    protected function cleanValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
