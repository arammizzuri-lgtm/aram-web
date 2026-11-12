<?php

namespace App\Filament\Resources\MissingPages;

use App\Filament\Resources\MissingPages\Pages\ManageMissingPages;
use App\Models\MissingPage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Broken links: the addresses visitors asked for that do not exist
 * (App\Http\Middleware\RecordMissingPage), most asked first, with the page
 * that sent them. One sent from this site is a link to fix here; one from
 * elsewhere is a page to bring back, or to point somewhere. "Fixed" clears it.
 */
class MissingPageResource extends Resource
{
    protected static ?string $model = MissingPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLinkSlash;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Broken links';

    protected static ?string $modelLabel = 'broken link';

    protected static ?string $pluralModelLabel = 'broken links';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('hits', 'desc')
            ->description('Pages visitors asked for that do not exist, counted as they happen. "From this site" means a link on your own pages leads nowhere — fix that link. Once dealt with, mark it Fixed.')
            ->columns([
                TextColumn::make('path')->label('Address')->searchable()->wrap()
                    ->url(fn (MissingPage $r) => $r->url(), shouldOpenInNewTab: true)->fontFamily('mono'),
                TextColumn::make('hits')->label('Times')->sortable()->alignEnd(),
                TextColumn::make('referrer')->label('Came from')->wrap()->limit(70)
                    ->placeholder('typed in, a bookmark, or an app')
                    ->badge(fn (MissingPage $r) => $r->fromThisSite())
                    ->color(fn (MissingPage $r) => $r->fromThisSite() ? 'warning' : null)
                    ->description(fn (MissingPage $r) => $r->fromThisSite() ? 'From this site — a link to fix' : null),
                TextColumn::make('last_seen_at')->label('Last')->since()->sortable(),
                TextColumn::make('first_seen_at')->label('First')->date('j M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('ours')->label('From this site only')
                    ->query(fn (Builder $q) => $q->fromThisSite()),
            ])
            ->recordActions([
                Action::make('open')->label('Open')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (MissingPage $r) => $r->url(), shouldOpenInNewTab: true),
                DeleteAction::make()->label('Fixed')->icon(Heroicon::OutlinedCheck)->modalHeading('Clear this address from the list?'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()->label('Mark fixed')]),
            ])
            ->emptyStateHeading('No broken links')
            ->emptyStateDescription('When a visitor asks for a page that does not exist, it appears here.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageMissingPages::route('/')];
    }
}
