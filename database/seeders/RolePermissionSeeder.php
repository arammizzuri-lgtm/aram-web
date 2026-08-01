<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The five roles from docs/06-USER-FLOWS.md §F14 and their permissions.
 *
 * The distinction that matters commercially is `view_cost`: Sales and Warehouse
 * must never see landed cost, supplier pricing or margin. Those figures are what
 * a departing employee could hand to a competitor, so the restriction is a
 * permission gate rather than a UI convention.
 */
class RolePermissionSeeder extends Seeder
{
    /** Resources that get the standard verb set. */
    private const RESOURCES = [
        'product', 'product_category', 'brand', 'unit', 'price_tier',
        'supplier', 'supplier_product', 'price_list_import',
        'purchase_order', 'supplier_bill', 'supplier_payment',
        'shipment', 'freight_forwarder', 'landed_cost',
        'warehouse', 'stock', 'stock_adjustment', 'stock_transfer', 'goods_receipt',
        'customer', 'quotation', 'sales_order', 'delivery_note', 'invoice', 'payment', 'sales_return',
        'expense', 'bank_account',
        'report', 'user', 'setting',
    ];

    private const VERBS = ['view_any', 'view', 'create', 'update', 'delete'];

    /** Permissions that gate an action rather than a resource. */
    private const ABILITIES = [
        'view_cost',            // landed cost, supplier prices, margin
        'approve_credit',       // let a sales order exceed a customer's limit
        'finalise_landed_cost', // lock a shipment and post its revaluation
        'post_document',        // move a document from confirmed to posted
        'manage_exchange_rates',
        'export_data',
        'use_ai_assistant',
        'view_activity_log',
        'manage_backups',
    ];

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach ($this->allPermissions() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Roles are synced by permission name, which is resolved from the cached
        // collection — so it has to be rebuilt now the new permissions exist.
        $registrar->forgetCachedPermissions();

        $this->owner();
        $this->manager();
        $this->sales();
        $this->warehouse();
        $this->accountant();
    }

    /** @return array<string> */
    private function allPermissions(): array
    {
        $permissions = self::ABILITIES;

        foreach (self::RESOURCES as $resource) {
            foreach (self::VERBS as $verb) {
                $permissions[] = "{$verb}_{$resource}";
            }
        }

        return $permissions;
    }

    private function owner(): void
    {
        // Owner is granted everything via a Gate::before hook in AppServiceProvider,
        // so the role stays correct as new permissions are added in later phases.
        Role::findOrCreate('owner', 'web');
    }

    private function manager(): void
    {
        $role = Role::findOrCreate('manager', 'web');

        $role->syncPermissions([
            ...$this->for(self::RESOURCES, exclude: ['user', 'setting']),
            ...$this->for(['user', 'setting'], only: ['view_any', 'view']),
            'view_cost', 'approve_credit', 'finalise_landed_cost', 'post_document',
            'manage_exchange_rates', 'export_data', 'use_ai_assistant', 'view_activity_log',
        ]);
    }

    private function sales(): void
    {
        $role = Role::findOrCreate('sales', 'web');

        $role->syncPermissions([
            // Read-only on the catalogue; no cost data anywhere.
            ...$this->for(['product', 'product_category', 'brand', 'unit', 'price_tier', 'stock'], only: ['view_any', 'view']),
            ...$this->for(['customer', 'quotation', 'sales_order'], only: ['view_any', 'view', 'create', 'update']),
            ...$this->for(['invoice'], only: ['view_any', 'view', 'create']),
            ...$this->for(['delivery_note', 'sales_return', 'shipment'], only: ['view_any', 'view']),
            'use_ai_assistant',
        ]);
    }

    private function warehouse(): void
    {
        $role = Role::findOrCreate('warehouse', 'web');

        $role->syncPermissions([
            ...$this->for(['product', 'product_category', 'unit', 'supplier', 'purchase_order', 'sales_order'], only: ['view_any', 'view']),
            ...$this->for(['stock', 'goods_receipt', 'stock_adjustment', 'stock_transfer', 'warehouse'], exclude: []),
            ...$this->for(['shipment'], only: ['view_any', 'view', 'update']),
            ...$this->for(['delivery_note'], only: ['view_any', 'view', 'create', 'update']),
        ]);
    }

    private function accountant(): void
    {
        $role = Role::findOrCreate('accountant', 'web');

        $role->syncPermissions([
            ...$this->for(['invoice', 'payment', 'expense', 'bank_account', 'supplier_bill', 'supplier_payment', 'sales_return'], exclude: []),
            ...$this->for(['customer', 'supplier', 'product', 'stock', 'purchase_order', 'sales_order', 'shipment', 'landed_cost'], only: ['view_any', 'view']),
            ...$this->for(['report'], exclude: []),
            'view_cost', 'post_document', 'export_data', 'use_ai_assistant', 'manage_exchange_rates',
        ]);
    }

    /**
     * Expand resources into permission names.
     *
     * @param  array<string>  $resources
     * @param  array<string>|null  $only  Limit to these verbs.
     * @param  array<string>  $exclude  Resources to drop entirely.
     * @return array<string>
     */
    private function for(array $resources, ?array $only = null, array $exclude = []): array
    {
        $verbs = $only ?? self::VERBS;
        $names = [];

        foreach (array_diff($resources, $exclude) as $resource) {
            foreach ($verbs as $verb) {
                $names[] = "{$verb}_{$resource}";
            }
        }

        return $names;
    }
}
