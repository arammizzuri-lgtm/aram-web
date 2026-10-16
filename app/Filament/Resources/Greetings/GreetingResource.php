<?php

namespace App\Filament\Resources\Greetings;

use App\Filament\Resources\Greetings\Pages\CreateGreeting;
use App\Filament\Resources\Greetings\Pages\EditGreeting;
use App\Filament\Resources\Greetings\Pages\ListGreetings;
use App\Filament\Resources\Greetings\Schemas\GreetingForm;
use App\Filament\Resources\Greetings\Tables\GreetingsTable;
use App\Models\Greeting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/** Holiday cards: Newroz, Eid, the New Year — made on /card and sent on WhatsApp. */
class GreetingResource extends Resource
{
    protected static ?string $model = Greeting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Manage';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Holiday cards';

    protected static ?string $modelLabel = 'holiday card';

    public static function form(Schema $schema): Schema
    {
        return GreetingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GreetingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGreetings::route('/'),
            'create' => CreateGreeting::route('/create'),
            'edit' => EditGreeting::route('/{record}/edit'),
        ];
    }
}
