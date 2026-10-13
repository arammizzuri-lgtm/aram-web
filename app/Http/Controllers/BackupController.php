<?php

namespace App\Http\Controllers;

use App\Support\DatabaseBackup;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Database downloads for Manage → Backups. Registered inside the panel's
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
