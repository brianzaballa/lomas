<?php

namespace App\Domain\Access;

/**
 * The permissions used for authorization, grouped for the role editor UI.
 */
final class Permission
{
    public const BORROWERS_VIEW = 'borrowers.view';

    public const BORROWERS_CREATE = 'borrowers.create';

    public const BORROWERS_UPDATE = 'borrowers.update';

    public const LOANS_VIEW = 'loans.view';

    public const LOANS_CREATE = 'loans.create';

    public const LOANS_UPDATE = 'loans.update';

    public const LOANS_APPROVE = 'loans.approve';

    public const LOANS_RELEASE = 'loans.release';

    public const LOANS_CANCEL = 'loans.cancel';

    public const PAYMENTS_VIEW = 'payments.view';

    public const PAYMENTS_CREATE = 'payments.create';

    public const PAYMENTS_REVERSE = 'payments.reverse';

    public const REPORTS_VIEW = 'reports.view';

    public const REPORTS_EXPORT = 'reports.export';

    public const SETTINGS_VIEW = 'settings.view';

    public const SETTINGS_MANAGE = 'settings.manage';

    public const USERS_MANAGE = 'users.manage';

    public const ROLES_MANAGE = 'roles.manage';

    public const AUDIT_VIEW = 'audit.view';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_merge([], ...array_values(self::grouped()));
    }

    /**
     * @return array<string, list<string>>
     */
    public static function grouped(): array
    {
        return [
            'Borrowers' => [
                self::BORROWERS_VIEW,
                self::BORROWERS_CREATE,
                self::BORROWERS_UPDATE,
            ],
            'Loans' => [
                self::LOANS_VIEW,
                self::LOANS_CREATE,
                self::LOANS_UPDATE,
                self::LOANS_APPROVE,
                self::LOANS_RELEASE,
                self::LOANS_CANCEL,
            ],
            'Payments' => [
                self::PAYMENTS_VIEW,
                self::PAYMENTS_CREATE,
                self::PAYMENTS_REVERSE,
            ],
            'Reports' => [
                self::REPORTS_VIEW,
                self::REPORTS_EXPORT,
            ],
            'Settings' => [
                self::SETTINGS_VIEW,
                self::SETTINGS_MANAGE,
            ],
            'Administration' => [
                self::USERS_MANAGE,
                self::ROLES_MANAGE,
                self::AUDIT_VIEW,
            ],
        ];
    }

    /**
     * Human readable labels, e.g. "settings.manage" becomes "Manage settings".
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::all() as $permission) {
            $labels[$permission] = mb_convert_case(str_replace('.', ' ', $permission), MB_CASE_TITLE, 'UTF-8');
        }

        return $labels;
    }

    private function __construct() {}
}
