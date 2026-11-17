<?php

namespace App\Filament\Resources\Drawings;

use App\Filament\Resources\Drawings\Pages\CreateDrawing;
use App\Filament\Resources\Drawings\Pages\EditDrawing;
use App\Filament\Resources\Drawings\Pages\ListDrawings;
use App\Filament\Resources\Drawings\Schemas\DrawingForm;
use App\Filament\Resources\Drawings\Tables\DrawingsTable;
use App\Models\Drawing;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Plans traced over a survey's scan (App\Models\Drawing): new ones, for an
 * essay's "Drawing" section, and corrections to the walls of the essays' own.
 */
class DrawingResource extends Resource
{
    protected static ?string $model = Drawing::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $recordTitleAttribute = 'title_en';

    protected static string|UnitEnum|null $navigationGroup = 'Portfolio';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Drawings';

    protected static ?string $modelLabel = 'drawing';

    public static function form(Schema $schema): Schema
    {
        return DrawingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DrawingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDrawings::route('/'),
            'create' => CreateDrawing::route('/create'),
            'edit' => EditDrawing::route('/{record}/edit'),
        ];
    }
}
