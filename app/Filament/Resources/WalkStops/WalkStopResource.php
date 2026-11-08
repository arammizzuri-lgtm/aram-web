<?php

namespace App\Filament\Resources\WalkStops;

use App\Filament\Resources\WalkStops\Pages\CreateWalkStop;
use App\Filament\Resources\WalkStops\Pages\EditWalkStop;
use App\Filament\Resources\WalkStops\Pages\ListWalkStops;
use App\Filament\Resources\WalkStops\Schemas\WalkStopForm;
use App\Filament\Resources\WalkStops\Tables\WalkStopsTable;
use App\Models\WalkStop;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The stops of the Erbil architecture walk (/erbil-walk), in order:
 * drag to reorder; each has its texts in three languages, a pin and a photo.
 */
class WalkStopResource extends Resource
{
    protected static ?string $model = WalkStop::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $recordTitleAttribute = 'name_en';

    protected static string|UnitEnum|null $navigationGroup = 'Manage';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationLabel = 'Erbil walk';

    protected static ?string $modelLabel = 'walk stop';

    protected static ?string $pluralModelLabel = 'Erbil walk — stops';

    public static function form(Schema $schema): Schema
    {
        return WalkStopForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WalkStopsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWalkStops::route('/'),
            'create' => CreateWalkStop::route('/create'),
            'edit' => EditWalkStop::route('/{record}/edit'),
        ];
    }
}