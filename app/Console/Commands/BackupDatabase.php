<?php

namespace App\Console\Commands;

use App\Support\DatabaseBackup;
use Illuminate\Console\Command;
use Throwable;

/**
 * Snapshot an SQLite database before it is migrated. .cpanel.yml runs it
 * ahead of every `migrate --force`, for the site and for the ERP:
 *     php artisan backup:db --label=deploy
 *     php artisan backup:db --source=erp/database/database.sqlite --dir=erp/storage/app/private/backups --label=deploy
 * Relative paths are taken from the site's root.
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:db
        {--source= : The SQLite file to copy (default: the site database)}
        {--dir= : Where to keep the copies (default: storage/app/private/backups)}
        {--label=manual : A word saved in the file name, e.g. deploy}
        {--keep=10 : How many copies to keep in that folder}';

    protected $description = 'Copy the SQLite database into the backups folder, keeping the newest few';

    public function handle(): int
    {
        $source = $this->resolve($this->option('source')) ?? DatabaseBackup::source();
        $dir = $this->resolve($this->option('dir')) ?? DatabaseBackup::dir();

        try {
            $path = DatabaseBackup::take($source, $dir, (string) $this->option('label'), max(1, (int) $this->option('keep')));
        } catch (Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($path === null) {
            $this->info("No database at {$source} yet — nothing to back up.");

            return self::SUCCESS;
        }

        $this->info('Backed up to '.$path.' ('.number_format(filesize($path) / 1024).' KB).');

        return self::SUCCESS;
    }

    private function resolve(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        // absolute on Unix or Windows
        return preg_match('#^([a-zA-Z]:)?[/\\\\]#', $path) ? $path : base_path($path);
    }
}
