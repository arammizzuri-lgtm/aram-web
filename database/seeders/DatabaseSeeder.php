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
        $this->call([
            FoundationSeeder::class,
            ReferenceDataSeeder::class,
            RolePermissionSeeder::class,
            DemoDataSeeder::class,
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
