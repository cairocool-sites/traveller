<?php

return [
    'common' => [
        'yes' => 'نعم',
        'no' => 'لا',
        'not_available' => 'غير متاح',
    ],
    'dashboard' => [
        'title' => 'لوحة الإدارة',
        'navigation_label' => 'لوحة الإدارة',
        'stats' => [
            'project' => 'المشروع',
            'locale' => 'اللغة',
            'timezone' => 'المنطقة الزمنية',
            'currency' => 'العملة الافتراضية',
            'role' => 'الدور',
        ],
    ],
    'locales' => [
        'ar' => 'العربية',
        'en' => 'الإنجليزية',
    ],
    'users' => [
        'model_label' => 'مستخدم',
        'plural_model_label' => 'المستخدمون',
        'navigation_label' => 'المستخدمون',
        'fields' => [
            'name' => 'الاسم',
            'email' => 'البريد الإلكتروني',
            'password' => 'كلمة المرور',
            'is_active' => 'نشط',
            'preferred_locale' => 'اللغة المفضلة',
            'roles' => 'الأدوار',
        ],
        'actions' => [
            'activate' => 'تفعيل',
            'deactivate' => 'تعطيل',
        ],
    ],
    'roles' => [
        'title' => 'الأدوار والصلاحيات',
        'navigation_label' => 'الأدوار',
        'role' => 'الدور',
        'names' => [
            'super_admin' => 'مدير عام للنظام',
            'general_manager' => 'المدير العام',
            'operations_admin' => 'مدير العمليات',
            'reservation_manager' => 'مدير الحجوزات',
            'reservation_agent' => 'موظف الحجوزات',
            'accountant' => 'المحاسب',
            'customer_support' => 'دعم العملاء',
            'content_manager' => 'مدير المحتوى',
            'api_manager' => 'مدير الربط التقني',
            'auditor' => 'مراجع',
        ],
    ],
    'permissions' => [
        'access_admin' => 'دخول لوحة الإدارة',
        'view_users' => 'عرض المستخدمين',
        'create_users' => 'إنشاء مستخدمين',
        'update_users' => 'تعديل المستخدمين',
        'deactivate_users' => 'تعطيل المستخدمين',
        'assign_roles' => 'إسناد الأدوار',
        'view_roles' => 'عرض الأدوار',
        'manage_roles' => 'إدارة الأدوار',
        'view_audit_logs' => 'عرض سجل المراجعة',
        'manage_system_settings' => 'إدارة إعدادات النظام',
    ],
];
