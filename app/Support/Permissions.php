<?php

namespace App\Support;

class Permissions
{
    public const ALL = '*';

    public const MEMBERS_VIEW = 'members.view';

    public const MEMBERS_CREATE = 'members.create';

    public const COLLECTION_VIEW = 'collection.view';

    public const COLLECTION_COLLECT = 'collection.collect';

    public const PAYMENTS_APPROVE = 'payments.approve';

    public const FUNDS_VIEW = 'funds.view';

    public const FUNDS_MANAGE = 'funds.manage';

    public const EXPENSES_VIEW = 'expenses.view';

    public const EXPENSES_CREATE = 'expenses.create';

    public const EXPENSE_HEADS_MANAGE = 'expense_heads.manage';

    public const REPORTS_VIEW = 'reports.view';

    public const JOIN_REQUESTS_MANAGE = 'join_requests.manage';

    public const COMMITTEE_MANAGE = 'committee.manage';

    public const MEETINGS_VIEW = 'meetings.view';

    public const MEETINGS_MANAGE = 'meetings.manage';

    public const ORGANIZATION_MANAGE = 'organization.manage';

    public const ROLES_MANAGE = 'roles.manage';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            self::MEMBERS_VIEW,
            self::MEMBERS_CREATE,
            self::COLLECTION_VIEW,
            self::COLLECTION_COLLECT,
            self::PAYMENTS_APPROVE,
            self::FUNDS_VIEW,
            self::FUNDS_MANAGE,
            self::EXPENSES_VIEW,
            self::EXPENSES_CREATE,
            self::EXPENSE_HEADS_MANAGE,
            self::REPORTS_VIEW,
            self::JOIN_REQUESTS_MANAGE,
            self::COMMITTEE_MANAGE,
            self::MEETINGS_VIEW,
            self::MEETINGS_MANAGE,
            self::ORGANIZATION_MANAGE,
            self::ROLES_MANAGE,
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function groups(): array
    {
        return [
            'Members' => [self::MEMBERS_VIEW, self::MEMBERS_CREATE],
            'Collection' => [self::COLLECTION_VIEW, self::COLLECTION_COLLECT, self::PAYMENTS_APPROVE],
            'Funds' => [self::FUNDS_VIEW, self::FUNDS_MANAGE],
            'Expenses' => [self::EXPENSES_VIEW, self::EXPENSES_CREATE, self::EXPENSE_HEADS_MANAGE],
            'Reports' => [self::REPORTS_VIEW],
            'Join requests' => [self::JOIN_REQUESTS_MANAGE],
            'Committee' => [self::COMMITTEE_MANAGE],
            'Meetings' => [self::MEETINGS_VIEW, self::MEETINGS_MANAGE],
            'Settings' => [self::ORGANIZATION_MANAGE, self::ROLES_MANAGE],
        ];
    }

    /**
     * @return list<string>
     */
    public static function admin(): array
    {
        return [self::ALL];
    }

    /**
     * @return list<string>
     */
    public static function collector(): array
    {
        return [
            self::MEMBERS_VIEW,
            self::MEMBERS_CREATE,
            self::COLLECTION_VIEW,
            self::COLLECTION_COLLECT,
            self::PAYMENTS_APPROVE,
            self::FUNDS_VIEW,
            self::FUNDS_MANAGE,
            self::EXPENSES_VIEW,
            self::EXPENSES_CREATE,
            self::REPORTS_VIEW,
            self::JOIN_REQUESTS_MANAGE,
            self::MEETINGS_VIEW,
            self::MEETINGS_MANAGE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function member(): array
    {
        return [];
    }

    /**
     * @param  list<string>|null  $permissions
     */
    public static function allows(?array $permissions, string $permission): bool
    {
        $permissions ??= [];
        if (in_array(self::ALL, $permissions, true)) {
            return true;
        }

        return in_array($permission, $permissions, true);
    }

    /**
     * @param  list<string>|null  $permissions
     * @return list<string>
     */
    public static function expand(?array $permissions): array
    {
        $permissions ??= [];
        if (in_array(self::ALL, $permissions, true)) {
            return self::keys();
        }

        return array_values(array_intersect(self::keys(), $permissions));
    }
}
