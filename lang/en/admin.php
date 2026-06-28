<?php

return [
    'common' => [
        'yes' => 'Yes',
        'no' => 'No',
        'not_available' => 'Not available',
    ],
    'dashboard' => [
        'title' => 'Admin Dashboard',
        'navigation_label' => 'Dashboard',
        'stats' => [
            'project' => 'Project',
            'locale' => 'Locale',
            'timezone' => 'Timezone',
            'currency' => 'Default currency',
            'role' => 'Role',
        ],
    ],
    'locales' => [
        'ar' => 'Arabic',
        'en' => 'English',
    ],
    'users' => [
        'model_label' => 'User',
        'plural_model_label' => 'Users',
        'navigation_label' => 'Users',
        'fields' => [
            'name' => 'Name',
            'email' => 'Email',
            'password' => 'Password',
            'is_active' => 'Active',
            'preferred_locale' => 'Preferred locale',
            'roles' => 'Roles',
        ],
        'actions' => [
            'activate' => 'Activate',
            'deactivate' => 'Deactivate',
        ],
    ],
    'roles' => [
        'title' => 'Roles and Permissions',
        'navigation_label' => 'Roles',
        'role' => 'Role',
        'names' => [
            'super_admin' => 'Super Admin',
            'general_manager' => 'General Manager',
            'operations_admin' => 'Operations Admin',
            'reservation_manager' => 'Reservation Manager',
            'reservation_agent' => 'Reservation Agent',
            'accountant' => 'Accountant',
            'customer_support' => 'Customer Support',
            'content_manager' => 'Content Manager',
            'api_manager' => 'API Manager',
            'auditor' => 'Auditor',
        ],
    ],
    'permissions' => [
        'access_admin' => 'Access admin',
        'view_users' => 'View users',
        'create_users' => 'Create users',
        'update_users' => 'Update users',
        'deactivate_users' => 'Deactivate users',
        'assign_roles' => 'Assign roles',
        'view_roles' => 'View roles',
        'manage_roles' => 'Manage roles',
        'view_audit_logs' => 'View audit logs',
        'manage_system_settings' => 'Manage system settings',
    ],
];
