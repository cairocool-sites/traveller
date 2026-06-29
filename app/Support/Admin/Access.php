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
        'view_countries',
        'manage_countries',
        'view_cities',
        'manage_cities',
        'view_areas',
        'manage_areas',
        'view_currencies',
        'manage_currencies',
        'view_exchange_rates',
        'manage_exchange_rates',
        'view_facilities',
        'manage_facilities',
        'view_hotels',
        'manage_hotels',
        'publish_hotels',
        'manage_hotel_media',
        'manage_hotel_facilities',
        'manage_hotel_policies',
        'view_suppliers',
        'manage_suppliers',
        'manage_supplier_credentials',
        'run_supplier_health_checks',
        'view_supplier_logs',
        'view_sensitive_supplier_logs',
        'view_search_sessions',
    ];

    public const ROLE_PERMISSIONS = [
        'super_admin' => self::PERMISSIONS,
        'general_manager' => self::PERMISSIONS,
        'operations_admin' => [
            'access_admin',
            'view_users',
            'view_countries',
            'manage_countries',
            'view_cities',
            'manage_cities',
            'view_areas',
            'manage_areas',
            'view_hotels',
            'manage_hotels',
            'manage_hotel_facilities',
            'manage_hotel_policies',
            'view_suppliers',
            'view_supplier_logs',
        ],
        'reservation_manager' => [
            'access_admin',
        ],
        'reservation_agent' => [
            'access_admin',
        ],
        'accountant' => [
            'access_admin',
            'view_currencies',
            'view_exchange_rates',
            'manage_exchange_rates',
        ],
        'customer_support' => [
            'access_admin',
        ],
        'content_manager' => [
            'access_admin',
            'view_countries',
            'manage_countries',
            'view_cities',
            'manage_cities',
            'view_areas',
            'manage_areas',
            'view_facilities',
            'manage_facilities',
            'view_hotels',
            'manage_hotels',
            'publish_hotels',
            'manage_hotel_media',
            'manage_hotel_facilities',
            'manage_hotel_policies',
        ],
        'api_manager' => [
            'access_admin',
            'view_suppliers',
            'manage_suppliers',
            'manage_supplier_credentials',
            'run_supplier_health_checks',
            'view_supplier_logs',
            'view_sensitive_supplier_logs',
            'view_search_sessions',
        ],
        'auditor' => [
            'access_admin',
            'view_users',
            'view_roles',
            'view_audit_logs',
            'view_countries',
            'view_cities',
            'view_areas',
            'view_currencies',
            'view_exchange_rates',
            'view_facilities',
            'view_hotels',
            'view_suppliers',
            'view_supplier_logs',
            'view_search_sessions',
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
