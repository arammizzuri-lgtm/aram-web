<?php

namespace App\Http\Controllers;

use App\Support\DatabaseBackup;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Database downloads for System → Backups. Registered inside the panel's
 * authenticated routes (AdminPanelProvider), so only a signed-in studio
 * account reaches them.
 */
class BackupController extends Controller
{
    /** A copy of the database as it is right now. */
    public function fresh(): BinaryFileResponse
    {
        $tmp = storage_path('app/private/backup-'.bin2hex(random_bytes(6)).'.sqlite');
        DatabaseBackup::copy(DatabaseBackup::source(), $tmp);

        return $this->send($tmp, 'aram-mizuri-database-'.now()->format('Y-m-d-Hi').'.sqlite')
            ->deleteFileAfterSend();
    }

    /**
     * Everything in one zip: a copy of the database, every uploaded photo and
     * file (the public disk), and the private files — client photos, CVs,
     * drawings — but not the database copies already kept in backups/.
     */
    public function everything(): BinaryFileResponse
    {
        @set_time_limit(600);   // a few hundred MB of photographs to read

        $stamp = bin2hex(random_bytes(6));
        $db = storage_path("app/private/backup-{$stamp}.sqlite");
        $zipPath = storage_path("app/private/backup-{$stamp}.zip");
        DatabaseBackup::copy(DatabaseBackup::source(), $db);

        $zip = new \ZipArchive;
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::EXCL) !== true) {
            @unlink($db);
            throw new RuntimeException('Could not start the backup zip.');
        }
        $zip->addFile($db, 'database.sqlite');
        $zip->addFromString('README.txt', implode("\n", [
            'Aram Mizuri - everything the site holds, '.now()->format('j M Y, H:i').'.',
            '',
            'database.sqlite  -> aram-web/database/database.sqlite',
            'files/           -> aram-web/storage/app/public/   (photographs and uploads)',
            'private/         -> aram-web/storage/app/private/  (client photos, CVs, drawings)',
            '',
        ]));
        foreach (['files' => 'public', 'private' => 'local'] as $folder => $disk) {
            foreach (Storage::disk($disk)->allFiles() as $file) {
                if ($disk === 'local' && (str_starts_with($file, 'backups/') || str_starts_with($file, 'backup-'))) {
                    continue;
                }
                $zip->addFile(Storage::disk($disk)->path($file), "{$folder}/{$file}");
                $zip->setCompressionName("{$folder}/{$file}", \ZipArchive::CM_STORE);   // photos are compressed already
            }
        }
        $closed = $zip->close();
        @unlink($db);
        if (! $closed) {
            @unlink($zipPath);
            throw new RuntimeException('Could not write the backup zip.');
        }

        return response()->download($zipPath, 'aram-mizuri-everything-'.now()->format('Y-m-d-Hi').'.zip', [
            'Content-Type' => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ])->setPrivate()->deleteFileAfterSend();
    }

    /** One of the copies taken before a deploy. */
    public function download(string $name): BinaryFileResponse
    {
        $path = DatabaseBackup::path($name);
        abort_if($path === null, 404);

        return $this->send($path, 'aram-mizuri-'.$name);
    }

    private function send(string $path, string $as): BinaryFileResponse
    {
        // download() marks the response public; this one must never be cached.
        return response()->download($path, $as, [
            'Content-Type' => 'application/vnd.sqlite3',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ])->setPrivate();
    }
}
