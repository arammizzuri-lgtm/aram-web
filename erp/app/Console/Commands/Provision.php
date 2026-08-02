<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\CrystalCatalogueSeeder;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Brings a freshly deployed database to the point where someone can sign in.
 *
 * This exists because the host has no shell: everything that would normally be
 * typed once after the first deploy has to happen inside the deploy itself. It
 * is written to be run on every deploy, so it must stay safe to repeat — it
 * seeds starting data only into an empty database, and never invents a password.
 */
class Provision extends Command
{
    protected $signature = 'erp:provision';

    protected $description = 'Seed a new database and create the first administrator from the environment';

    public function handle(): int
    {
        if (! Schema::hasTable('users')) {
            $this->components->error('The database has not been migrated yet; run `php artisan migrate --force` first.');

            return self::FAILURE;
        }

        if (! User::query()->exists()) {
            /*
             * Currencies, units, customer types and the catalogue — the data the
             * system cannot be opened without.
             *
             * Only into an empty database. These seeders write with
             * updateOrCreate, and re-running them against a live system would
             * quietly undo every rename and reprice made since the last deploy.
             */
            $this->components->info('Empty database — seeding starting data.');

            foreach ([FoundationSeeder::class, ReferenceDataSeeder::class, CrystalCatalogueSeeder::class] as $seeder) {
                $this->callSilent('db:seed', ['--class' => $seeder, '--force' => true]);
            }
        }

        /*
         * Roles and permissions are defined in code rather than by the user, so
         * they are re-synced on every deploy: a release that adds a permission
         * grants it here, instead of waiting for someone to notice it missing.
         */
        $this->callSilent('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);

        return $this->provisionAdministrator();
    }

    private function provisionAdministrator(): int
    {
        $email = trim((string) config('erp.admin.email'));
        $password = (string) config('erp.admin.password');
        $name = trim((string) config('erp.admin.name')) ?: 'Owner';

        if ($email === '') {
            $this->components->info('No ERP_ADMIN_EMAIL set — leaving accounts alone.');

            return self::SUCCESS;
        }

        $user = User::query()->where('email', $email)->first();

        // An account with no password is an account anyone can walk into, so a
        // missing one is a reason to stop rather than to improvise.
        if (! $user && $password === '') {
            $this->components->warn("No account for {$email} and no ERP_ADMIN_PASSWORD set — nothing created.");

            return self::SUCCESS;
        }

        if ($password !== '' && strlen($password) < 12) {
            $this->components->warn('ERP_ADMIN_PASSWORD is shorter than 12 characters. This account is the whole of the ERP.');
        }

        if (! $user) {
            $user = new User(['name' => $name, 'email' => $email]);
        }

        /*
         * Blank means "leave the password as it is". That is what lets the
         * password be wiped from .env after the first sign-in without the next
         * deploy resetting the account to nothing.
         *
         * And when it is not blank, it is written only if it is not already the
         * password in force. Hashing is salted, so re-hashing the same password
         * stores a different string every time — and the panel runs
         * AuthenticateSession, which reads a changed hash as "signed in
         * elsewhere" and ends every session there is. Left as an unconditional
         * write, simply leaving the password in .env signs you out on every
         * deploy, mid-work, with nothing to explain it.
         */
        if ($password !== '' && ! Hash::check($password, (string) $user->password)) {
            $user->password = $password;
        }

        $user->is_active = true;
        $user->save();

        $user->syncRoles('owner');

        $this->components->info("Owner account ready: {$email}");

        return self::SUCCESS;
    }
}
