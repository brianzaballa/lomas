<?php

namespace App\Http\Requests\Administration;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Domain\Access\Permission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

class UpdateUserRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::USERS_MANAGE) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            ...$this->profileRules($user->getKey()),
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }

    /**
     * An administrator may not remove their own access to user management.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var User $user */
            $user = $this->route('user');

            if (! $user->is($this->user())) {
                return;
            }

            $keepsAccess = Role::query()
                ->whereIn('name', $this->input('roles', []))
                ->whereHas('permissions', fn ($query) => $query->where('name', Permission::USERS_MANAGE))
                ->exists();

            if (! $keepsAccess) {
                $validator->errors()->add('roles', 'You cannot remove your own access to user management.');
            }
        });
    }
}
