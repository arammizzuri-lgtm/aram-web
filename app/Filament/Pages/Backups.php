<?php

namespace App\Filament\Pages;

use App\Support\DatabaseBackup;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * System → Backups: download the database as it is now, or as it was before
 * any of the last deploys migrated it (those copies are taken by .cpanel.yml
 * through `php artisan backup:db`).
 */
class Backups extends Page
{
    protected string $view = 'filament.pages.backups';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Backups';

    protected ?string $subheading = 'Everything the admin holds — projects, enquiries, client rooms, settings — is in one database file.';

    /** @return list<array{name: string, size: int, time: int, label: string}> */
    public function getSnapshots(): array
    {
        return DatabaseBackup::files();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('fresh')
                ->label('Download a copy now')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(route('filament.studio-aks-panel.backup-fresh')),
        ];
    }
}
