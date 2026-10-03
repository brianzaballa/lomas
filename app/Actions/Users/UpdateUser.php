<?php

namespace App\Actions\Users;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Updates a user account. Historical attributes are written to the audit
 * trail so that a change to a login can be explained later on.
 */
class UpdateUser
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, email: string, password?: string|null}  $attributes
     * @param  list<string>  $roles
     */
    public function handle(User $user, array $attributes, array $roles): User
    {
        return DB::transaction(function () use ($user, $attributes, $roles): User {
            $old = ['name' => $user->name, 'email' => $user->email];
            $oldRoles = $user->getRoleNames()->values()->all();

            $user->fill([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                ...(($attributes['password'] ?? null) !== null ? ['password' => $attributes['password']] : []),
            ])->save();

            $user->syncRoles($roles);

            $new = ['name' => $user->name, 'email' => $user->email];

            if ($old !== $new) {
                $this->audit->recordUpdated(AuditAction::USER_UPDATED, $user, $old, $new);
            }

            if ($oldRoles !== $roles) {
                $this->audit->recordUpdated(AuditAction::USER_ROLES_UPDATED, $user, ['roles' => $oldRoles], ['roles' => $roles]);
            }

            return $user;
        });
    }
}
