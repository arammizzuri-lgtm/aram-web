<?php

namespace App\Filament\Resources\Essays;

use App\Filament\Resources\Essays\Pages\CreateEssay;
use App\Filament\Resources\Essays\Pages\EditEssay;
use App\Filament\Resources\Essays\Pages\ListEssays;
use App\Filament\Resources\Essays\Schemas\EssayForm;
use App\Filament\Resources\Essays\Tables\EssaysTable;
use App\Models\Essay;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The journal's essays written in the admin (/journal/{slug}): a title, a
 * line and a cover photograph for the card, and a page composed of the
 * heritage pages' sections, in three languages. Essays with drawings of
 * their own (the Citadel houses) are code, and are not listed here.
 */
class EssayResource extends Resource
{
    protected static ?string $model = Essay::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?string $recordTitleAttribute = 'title_en';

    protected static string|UnitEnum|null $navigationGroup = 'Portfolio';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Journal essays';

    protected static ?string $modelLabel = 'essay';

    protected static ?string $pluralModelLabel = 'Journal essays';

    public static function form(Schema $schema): Schema
    {
        return EssayForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EssaysTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEssays::route('/'),
            'create' => CreateEssay::route('/create'),
            'edit' => EditEssay::route('/{record}/edit'),
        ];
    }
}
