<?php

namespace App\Support;

use PDO;
use Throwable;

/**
 * Copies of the SQLite database, taken before every deploy migrates it and on
 * demand from the admin (Manage → Backups).
 *
 * The live database is the only copy of every enquiry, client room and setting,
 * and this host has no shell: a migration that fails halfway cannot be repaired
 * on the server, only undone by putting a copy back (cPanel → File Manager).
 *
 * A copy is made with VACUUM INTO, which SQLite writes from a single read
 * transaction, so it is consistent even if a visitor writes mid-copy; a plain
 * file copy is the fallback for an SQLite too old to have it (before 3.27).
 */
class DatabaseBackup
{
    /** Snapshots kept per folder; the oldest beyond this are deleted. */
    public const KEEP = 10;

    /** database-2026-10-13-091500-deploy.sqlite */
    private const NAME = '/^database-\d{4}-\d{2}-\d{2}-\d{6}-([a-z]+)\.sqlite$/';

    /** Where the site's own snapshots are kept: the private disk, never served directly. */
    public static function dir(): string
    {
        return storage_path('app/private/backups');
    }

    /** The site's database file. */
    public static function source(): string
    {
        return (string) config('database.connections.sqlite.database');
    }

    /**
     * Snapshot $source into $dir and keep only the newest $keep there.
     * Returns the new file's path, or null when there is no database yet (the
     * first deploy creates an empty file just before migrating).
     */
    public static function take(?string $source = null, ?string $dir = null, string $label = 'manual', int $keep = self::KEEP): ?string
    {
        $source ??= self::source();
        $dir ??= self::dir();

        if (! is_file($source) || filesize($source) === 0) {
            return null;
        }

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $label = preg_replace('/[^a-z]/', '', strtolower($label)) ?: 'manual';
        $target = $dir.DIRECTORY_SEPARATOR.'database-'.now()->format('Y-m-d-His').'-'.$label.'.sqlite';

        self::copy($source, $target);
        self::prune($dir, $keep);

        return $target;
    }

    /** A consistent copy of $source at $target, which must not exist yet. */
    public static function copy(string $source, string $target): void
    {
        try {
            $pdo = new PDO('sqlite:'.$source, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('VACUUM INTO '.$pdo->quote($target));
            $pdo = null;

            return;
        } catch (Throwable) {
            // older SQLite: fall through to a file copy
            if (is_file($target)) {
                @unlink($target);
            }
        }

        if (! @copy($source, $target)) {
            throw new \RuntimeException("Could not copy {$source} to {$target}.");
        }
    }

    /** Delete all but the newest $keep snapshots in $dir. */
    public static function prune(string $dir, int $keep = self::KEEP): void
    {
        $files = self::files($dir);

        foreach (array_slice($files, max(0, $keep)) as $file) {
            @unlink($dir.DIRECTORY_SEPARATOR.$file['name']);
        }
    }

    /**
     * The snapshots in $dir, newest first.
     *
     * @return list<array{name: string, size: int, time: int, label: string}>
     */
    public static function files(?string $dir = null): array
    {
        $dir ??= self::dir();
        $files = [];

        foreach (is_dir($dir) ? scandir($dir) : [] as $name) {
            if (! preg_match(self::NAME, $name, $m)) {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$name;
            $files[] = [
                'name' => $name,
                'size' => (int) filesize($path),
                'time' => (int) filemtime($path),
                'label' => $m[1],
            ];
        }

        // The timestamp is in the name, so names sort by age.
        usort($files, fn ($a, $b) => strcmp($b['name'], $a['name']));

        return $files;
    }

    /** The full path of one of the site's snapshots, or null for any other name. */
    public static function path(string $name): ?string
    {
        if (! preg_match(self::NAME, $name)) {
            return null;
        }
        $path = self::dir().DIRECTORY_SEPARATOR.$name;

        return is_file($path) ? $path : null;
    }
}
