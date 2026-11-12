<?php

namespace App\Filament\Resources\Landmarks;

use App\Filament\Resources\Landmarks\Pages\CreateLandmark;
use App\Filament\Resources\Landmarks\Pages\EditLandmark;
use App\Filament\Resources\Landmarks\Pages\ListLandmarks;
use App\Filament\Resources\Landmarks\Schemas\LandmarkForm;
use App\Filament\Resources\Landmarks\Tables\LandmarksTable;
use App\Models\Landmark;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The landmarks of Kurdistan's architectural heritage (/kurdistan-heritage):
 * a pin on the map of Greater Kurdistan and a page each, in three languages.
 * Drag to reorder — the list on the website follows this order.
 */
class LandmarkResource extends Resource
{
    protected static ?string $model = Landmark::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $recordTitleAttribute = 'name_en';

    protected static string|UnitEnum|null $navigationGroup = 'Portfolio';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Heritage landmarks';

    protected static ?string $modelLabel = 'landmark';

    protected static ?string $pluralModelLabel = "Kurdistan's architectural heritage";

    public static function form(Schema $schema): Schema
    {
        return LandmarkForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LandmarksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLandmarks::route('/'),
            'create' => CreateLandmark::route('/create'),
            'edit' => EditLandmark::route('/{record}/edit'),
        ];
    }
}
