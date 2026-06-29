# Admin Foundation

Phase 2 adds the internal administration foundation only. It does not add hotel, supplier, booking, payment, quotation, B2B, or external API features.

## Admin Panel

- URL: `/admin`
- Package: Filament
- Authentication: Laravel users
- Access control: active, email-verified users with the `access_admin` permission
- Default locale: Arabic
- Supported admin locales: Arabic and English

## Roles

- `super_admin`
- `general_manager`
- `operations_admin`
- `reservation_manager`
- `reservation_agent`
- `accountant`
- `customer_support`
- `content_manager`
- `api_manager`
- `auditor`

## Permissions

- `access_admin`
- `view_users`
- `create_users`
- `update_users`
- `deactivate_users`
- `assign_roles`
- `view_roles`
- `manage_roles`
- `view_audit_logs`
- `manage_system_settings`

Phase 3 reference-data permissions:

- `view_countries`
- `manage_countries`
- `view_cities`
- `manage_cities`
- `view_areas`
- `manage_areas`
- `view_currencies`
- `manage_currencies`
- `view_exchange_rates`
- `manage_exchange_rates`
- `view_facilities`
- `manage_facilities`

Phase 4 hotel-catalog permissions:

- `view_hotels`
- `manage_hotels`
- `publish_hotels`
- `manage_hotel_media`
- `manage_hotel_facilities`
- `manage_hotel_policies`

Phase 5 supplier permissions:

- `view_suppliers`
- `manage_suppliers`
- `manage_supplier_credentials`
- `run_supplier_health_checks`
- `view_supplier_logs`
- `view_sensitive_supplier_logs`

Allocation:

- `super_admin`: all permissions.
- `general_manager`: all permissions.
- `api_manager`: all supplier permissions.
- `operations_admin`: view suppliers and non-sensitive supplier logs.
- `auditor`: view suppliers and non-sensitive supplier logs only.
- Other roles receive no supplier permissions unless already covered by all-permission roles.

## First Super Admin

Set these values in local `.env` only:

```dotenv
ADMIN_NAME="Local Admin"
ADMIN_EMAIL="admin@example.test"
ADMIN_PASSWORD="Use-a-strong-local-password-123!"
```

Then run:

```bash
php artisan migrate --seed
```

The seeder validates password strength, creates roles and permissions idempotently, and does not log the password.

## Safety Rules

- Never commit `.env`.
- Never commit passwords, API keys, supplier credentials, payment credentials, or production secrets.
- Do not manually delete the final active `super_admin`.
- Role editing is intentionally limited in Phase 2; the roles matrix is read-only.
