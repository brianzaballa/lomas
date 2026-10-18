<?php

namespace App\Domain\Access;

/**
 * The initial roles described in AGENTS.md and their default permissions.
 *
 * Roles are seeded data; administrators may adjust the permission set of a
 * role from the Administration > Roles screen afterwards.
 */
final class Role
{
    public const ADMINISTRATOR = 'Administrator';

    public const LOAN_OFFICER = 'Loan Officer';

    public const COLLECTOR = 'Collector';

    public const ENCODER = 'Encoder';

    public const VIEWER = 'Viewer';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::defaultPermissions());
    }

    /**
     * @return array<string, list<string>>
     */
    public static function defaultPermissions(): array
    {
        return [
            self::ADMINISTRATOR => Permission::all(),

            self::LOAN_OFFICER => [
                Permission::BORROWERS_VIEW,
                Permission::BORROWERS_CREATE,
                Permission::BORROWERS_UPDATE,
                Permission::LOANS_VIEW,
                Permission::LOANS_CREATE,
                Permission::LOANS_UPDATE,
                
                Permission::PAYMENTS_VIEW,
                Permission::REPORTS_VIEW,
                Permission::REPORTS_EXPORT,
                Permission::REPORTS_POST,
                Permission::SETTINGS_VIEW,
            ],

            self::COLLECTOR => [
                Permission::BORROWERS_VIEW,
                Permission::LOANS_VIEW,
                Permission::PAYMENTS_VIEW,
                Permission::PAYMENTS_CREATE,
                Permission::REPORTS_VIEW,
            ],

            self::ENCODER => [
                Permission::BORROWERS_VIEW,
                Permission::BORROWERS_CREATE,
                Permission::BORROWERS_UPDATE,
                Permission::LOANS_VIEW,
                Permission::LOANS_CREATE,
                Permission::LOANS_UPDATE,
                Permission::PAYMENTS_VIEW,
                Permission::REPORTS_VIEW,
            ],

            self::VIEWER => [
                Permission::BORROWERS_VIEW,
                Permission::LOANS_VIEW,
                Permission::PAYMENTS_VIEW,
                Permission::REPORTS_VIEW,
            ],
        ];
    }

    private function __construct() {}
}
