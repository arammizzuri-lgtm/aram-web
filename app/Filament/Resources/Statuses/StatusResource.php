<?php

namespace App\Filament\Resources\Statuses;

use App\Filament\Resources\Statuses\Pages\CreateStatus;
use App\Filament\Resources\Statuses\Pages\EditStatus;
use App\Filament\Resources\Statuses\Pages\ListStatuses;
use App\Filament\Resources\Statuses\Schemas\StatusForm;
use App\Filament\Resources\Statuses\Tables\StatusesTable;
use App\Models\Status;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class StatusResource extends Resource
{
    protected static ?string $model = Status::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Portfolio';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Statuses';

    protected static ?string $navigationParentItem = 'Projects';

    protected static ?string $pluralModelLabel = 'Project Statuses';

    public static function form(Schema $schema): Schema
    {
        return StatusForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StatusesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStatuses::route('/'),
            'create' => CreateStatus::route('/create'),
            'edit' => EditStatus::route('/{record}/edit'),
        ];
    }
}
