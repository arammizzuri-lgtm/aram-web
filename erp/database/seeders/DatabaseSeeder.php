<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Model events are deliberately left enabled here: the permission registrar and
 * the Currency/Company caches all invalidate themselves through saved() hooks,
 * and suppressing those leaves the seeded data behind a stale cache.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        /*
         * The demo seeder is gone.
         *
         * It wrote fictional containers, stock and invoices for a business that
         * held inventory. Beyond being wrong, seeding fake deals is actively
         * harmful now: once a deal is invoiced and paid it cannot be deleted
         * without breaking the references hanging off it, so demo data would
         * become permanent furniture in a real ledger.
         */
        $this->call([
            FoundationSeeder::class,
            ReferenceDataSeeder::class,
            RolePermissionSeeder::class,
            CrystalCatalogueSeeder::class,
        ]);

        $owner = User::firstOrCreate(
            ['email' => 'owner@example.com'],
            [
                'name' => 'Owner',
                'password' => 'password',
                'is_active' => true,
            ],
        );

        $owner->syncRoles('owner');
    }
}
