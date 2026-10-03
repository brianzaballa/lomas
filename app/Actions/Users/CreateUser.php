<?php

namespace App\Actions\Users;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a user account and records the change in the audit trail.
 */
class CreateUser
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     * @param  list<string>  $roles
     */
    public function handle(array $attributes, array $roles): User
    {
        return DB::transaction(function () use ($attributes, $roles): User {
            $user = new User([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
            ]);

            // Administrator created accounts are usable immediately.
            $user->forceFill(['email_verified_at' => now()]);
            $user->save();

            $user->syncRoles($roles);

            $this->audit->recordCreated(AuditAction::USER_CREATED, $user, [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roles,
            ]);

            $this->audit->record(AuditAction::USER_ROLES_UPDATED, $user, newValues: ['roles' => $roles]);

            return $user;
        });
    }
}
