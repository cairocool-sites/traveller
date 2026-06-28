<?php

namespace App\Support\Admin;

final class Access
{
    public const ROLES = [
        'super_admin',
        'general_manager',
        'operations_admin',
        'reservation_manager',
        'reservation_agent',
        'accountant',
        'customer_support',
        'content_manager',
        'api_manager',
        'auditor',
    ];

    public const PERMISSIONS = [
        'access_admin',
        'view_users',
        'create_users',
        'update_users',
        'deactivate_users',
        'assign_roles',
        'view_roles',
        'manage_roles',
        'view_audit_logs',
        'manage_system_settings',
    ];

    public const ROLE_PERMISSIONS = [
        'super_admin' => self::PERMISSIONS,
        'general_manager' => self::PERMISSIONS,
        'operations_admin' => [
            'access_admin',
            'view_users',
        ],
        'reservation_manager' => [
            'access_admin',
        ],
        'reservation_agent' => [
            'access_admin',
        ],
        'accountant' => [
            'access_admin',
        ],
        'customer_support' => [
            'access_admin',
        ],
        'content_manager' => [
            'access_admin',
        ],
        'api_manager' => [
            'access_admin',
        ],
        'auditor' => [
            'access_admin',
            'view_users',
            'view_roles',
            'view_audit_logs',
        ],
    ];

    public static function assignableRolesFor(?string $roleName): array
    {
        if ($roleName === 'super_admin') {
            return self::ROLES;
        }

        return array_values(array_diff(self::ROLES, ['super_admin']));
    }
}
