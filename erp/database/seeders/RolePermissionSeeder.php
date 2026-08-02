<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Two roles, because two people use this.
 *
 * The old system had five, inherited from an org chart this business does not
 * have. Roles nobody occupies are not harmless: they are screens to maintain,
 * permissions to reason about, and somewhere for a mistake to hide.
 *
 * The only distinction that matters commercially is `view_cost`. The assistant
 * runs deals end to end — quoting, invoicing, tracking, taking payment — and
 * never sees what was paid in China or what was made. That hiding has to be
 * total: every screen, every report, every CSV export, and the AI assistant's
 * answers. A cost that leaks through one export defeats the whole arrangement.
 */
class RolePermissionSeeder extends Seeder
{
    /** @var list<string> */
    private const PERMISSIONS = [
        // The commercial boundary.
        'view_cost',            // supplier prices, purchase invoices, margin, profit

        // Ordinary work, available to both people.
        'manage_deals',
        'manage_quotations',
        'approve_quotation',
        'manage_consignments',
        'issue_invoice',
        'record_customer_payment',
        'export_data',
        'use_ai_assistant',

        // Owner only, beyond cost: money leaving the business, and settings.
        'record_supplier_payment',
        'manage_products',
        'manage_partners',
        'manage_settings',
        'manage_users',
    ];

    /** @var list<string> */
    private const ASSISTANT = [
        'manage_deals',
        'manage_quotations',
        'approve_quotation',
        'manage_consignments',
        'issue_invoice',
        'record_customer_payment',
        'use_ai_assistant',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Owner gets everything, including permissions added in later phases.
        Role::findOrCreate('owner', 'web')->syncPermissions(Permission::all());

        /*
         * The assistant can do the job, not see the economics.
         *
         * What is absent matters as much as what is present: no `view_cost`,
         * and no `record_supplier_payment` — paying China means seeing what
         * China charges, so those two cannot be separated.
         *
         * `export_data` is withheld for the same reason. An export is the
         * easiest place for a cost column to escape a screen that carefully
         * hid it.
         */
        Role::findOrCreate('assistant', 'web')->syncPermissions(self::ASSISTANT);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
