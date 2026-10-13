<?php

namespace Tests\Feature;

use App\Filament\Pages\Backups;
use App\Models\User;
use App\Support\DatabaseBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PDO;
use Tests\TestCase;

/**
 * The copy taken before each deploy migrates, and the admin's downloads.
 */
class BackupsTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmp = sys_get_temp_dir().'/am-backups-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->tmp.'/storage/app/private');
        $this->app->useStoragePath($this->tmp.'/storage');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        parent::tearDown();
    }

    /** A real database file holding one enquiry-like row. */
    private function liveDatabase(string $name = 'live.sqlite'): string
    {
        $path = $this->tmp.'/'.$name;
        $pdo = new PDO('sqlite:'.$path);
        $pdo->exec('CREATE TABLE things (name TEXT)');
        $pdo->exec("INSERT INTO things VALUES ('Shilan')");

        return $path;
    }

    private function rows(string $path): array
    {
        return (new PDO('sqlite:'.$path))->query('SELECT name FROM things')->fetchAll(PDO::FETCH_COLUMN);
    }

    public function test_the_deploy_copy_holds_the_data_and_only_the_newest_ten_are_kept(): void
    {
        $source = $this->liveDatabase();
        $dir = DatabaseBackup::dir();
        File::ensureDirectoryExists($dir);
        foreach (range(1, 12) as $i) {
            touch($dir.sprintf('/database-2026-01-%02d-120000-deploy.sqlite', $i));
        }
        touch($dir.'/notes.txt');                            // not ours: left alone

        $this->artisan('backup:db', ['--source' => $source, '--label' => 'deploy'])
            ->expectsOutputToContain('Backed up to')
            ->assertSuccessful();

        $files = DatabaseBackup::files();
        $this->assertCount(10, $files);
        $this->assertStringStartsWith('database-'.now()->format('Y-m-d'), $files[0]['name']);
        $this->assertSame('deploy', $files[0]['label']);
        $this->assertSame(['Shilan'], $this->rows($dir.'/'.$files[0]['name']));
        $this->assertFileDoesNotExist($dir.'/database-2026-01-03-120000-deploy.sqlite');
        $this->assertFileExists($dir.'/database-2026-01-04-120000-deploy.sqlite');
        $this->assertFileExists($dir.'/notes.txt');
    }

    public function test_the_erp_copy_goes_into_its_own_folder_and_a_missing_database_is_not_an_error(): void
    {
        $source = $this->liveDatabase('erp.sqlite');
        $dir = $this->tmp.'/erp-backups';

        $this->artisan('backup:db', ['--source' => $source, '--dir' => $dir, '--label' => 'deploy'])->assertSuccessful();
        $this->assertCount(1, DatabaseBackup::files($dir));
        $this->assertSame([], DatabaseBackup::files());

        $this->artisan('backup:db', ['--source' => $this->tmp.'/nothing.sqlite'])
            ->expectsOutputToContain('nothing to back up')
            ->assertSuccessful();
    }

    public function test_only_the_admin_downloads_copies_and_only_by_their_own_names(): void
    {
        config(['database.connections.sqlite.database' => $this->liveDatabase()]);
        $saved = DatabaseBackup::take(label: 'deploy');
        $name = basename($saved);
        $fresh = route('filament.studio-aks-panel.backup-fresh');
        $one = route('filament.studio-aks-panel.backup-download', $name);

        $this->get($fresh)->assertRedirect();               // a visitor is sent to the login
        $this->get($one)->assertRedirect();

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);
        $this->actingAs($admin);

        $this->get(Backups::getUrl())->assertOk()->assertSee('before a deploy')->assertSee('Download a copy now');

        $response = $this->get($fresh)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringContainsString('attachment; filename=aram-mizuri-database-', $response->headers->get('Content-Disposition'));
        $copy = $response->baseResponse->getFile()->getPathname();
        $this->assertSame(['Shilan'], $this->rows($copy));

        $this->get($one)->assertOk()->assertDownload('aram-mizuri-'.$name);

        $this->get(route('filament.studio-aks-panel.backup-download', 'database.sqlite'))->assertNotFound();
        $this->get(route('filament.studio-aks-panel.backup-download', 'database-2026-01-01-120000-deploy.sqlite'))->assertNotFound();
        $this->get('/studio-aks-panel/backups/..%2F..%2F.env')->assertNotFound();
    }
}
