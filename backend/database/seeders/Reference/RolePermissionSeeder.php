<?php

namespace Database\Seeders\Reference;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rôles et permissions (docs/SECURITY.md §2).
 */
class RolePermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        'dashboard.view', 'reports.view',
        'users.view', 'users.manage',
        'transfers.view', 'transfers.cancel',
        'refunds.request', 'refunds.approve',
        'disputes.manage',
        'reconciliation.manage', 'support.manage',
        'ledger.view', 'ledger.adjust.request', 'ledger.adjust.approve',
        'kyc.view', 'kyc.review',
        'risk.view', 'risk.manage', 'compliance.manage',
        'providers.view', 'providers.manage',
        'fees.manage', 'fx.manage',
        'audit.view', 'settings.manage', 'roles.manage',
    ];

    public const ROLES = [
        'customer' => [],
        'support' => ['dashboard.view', 'users.view', 'transfers.view', 'kyc.view', 'refunds.request', 'disputes.manage', 'support.manage'],
        'compliance' => ['dashboard.view', 'users.view', 'transfers.view', 'kyc.view', 'kyc.review', 'risk.view', 'risk.manage', 'compliance.manage', 'audit.view', 'reports.view', 'support.manage'],
        'finance' => ['dashboard.view', 'transfers.view', 'reconciliation.manage', 'ledger.view', 'ledger.adjust.request', 'ledger.adjust.approve', 'refunds.approve', 'reports.view', 'fees.manage', 'fx.manage'],
        'admin' => ['dashboard.view', 'users.view', 'users.manage', 'transfers.view', 'transfers.cancel', 'providers.view', 'providers.manage', 'fees.manage', 'fx.manage', 'settings.manage', 'audit.view', 'reports.view'],
        'super_admin' => self::PERMISSIONS,
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        foreach (self::ROLES as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }
    }
}
