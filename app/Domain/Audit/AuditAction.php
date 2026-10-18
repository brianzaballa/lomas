<?php

namespace App\Domain\Audit;

/**
 * Canonical audit action names.
 *
 * Actions are written as dot separated strings so that reports and the audit
 * log can be filtered by prefix, e.g. "loan.*" or "settings.updated".
 */
final class AuditAction
{
    public const BORROWER_CREATED = 'borrower.created';

    public const BORROWER_UPDATED = 'borrower.updated';

    public const LOAN_PRODUCT_CREATED = 'loan_product.created';

    public const LOAN_PRODUCT_UPDATED = 'loan_product.updated';

    public const DEDUCTION_TYPE_CREATED = 'deduction_type.created';

    public const DEDUCTION_TYPE_UPDATED = 'deduction_type.updated';

    public const USER_CREATED = 'user.created';

    public const USER_UPDATED = 'user.updated';

    public const USER_ROLES_UPDATED = 'user.roles_updated';

    public const USER_DELETED = 'user.deleted';

    public const ROLE_CREATED = 'role.created';

    public const ROLE_UPDATED = 'role.updated';

    public const ROLE_DELETED = 'role.deleted';

    public const SETTINGS_UPDATED = 'settings.updated';

    public const COLLECTION_POSTED = 'collection.posted';

    public const PAYMENT_POSTED = 'payment.posted';

    public const PAYMENT_REVERSED = 'payment.reversed';

    public const NCR_PERIOD_POSTED = 'ncr_period.posted';

    public const NCR_PERIOD_UNPOSTED = 'ncr_period.unposted';

    /**
     * Every action name known to the application.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::BORROWER_CREATED,
            self::BORROWER_UPDATED,
            self::LOAN_PRODUCT_CREATED,
            self::LOAN_PRODUCT_UPDATED,
            self::DEDUCTION_TYPE_CREATED,
            self::DEDUCTION_TYPE_UPDATED,
            self::USER_CREATED,
            self::USER_UPDATED,
            self::USER_ROLES_UPDATED,
            self::USER_DELETED,
            self::ROLE_CREATED,
            self::ROLE_UPDATED,
            self::ROLE_DELETED,
            self::SETTINGS_UPDATED,
            self::COLLECTION_POSTED,
            self::PAYMENT_POSTED,
            self::PAYMENT_REVERSED,
            self::NCR_PERIOD_POSTED,
            self::NCR_PERIOD_UNPOSTED,
        ];
    }

    private function __construct() {}
}
