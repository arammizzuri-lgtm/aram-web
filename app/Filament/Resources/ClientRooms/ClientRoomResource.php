<?php

namespace App\Filament\Resources\ClientRooms;

use App\Filament\Resources\ClientRooms\Pages\CreateClientRoom;
use App\Filament\Resources\ClientRooms\Pages\EditClientRoom;
use App\Filament\Resources\ClientRooms\Pages\ListClientRooms;
use App\Filament\Resources\ClientRooms\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\ClientRooms\RelationManagers\DrawingsRelationManager;
use App\Filament\Resources\ClientRooms\Schemas\ClientRoomForm;
use App\Filament\Resources\ClientRooms\Tables\ClientRoomsTable;
use App\Models\ClientRoom;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Client rooms: each client's private page for their project — progress,
 * drawings to approve, payment stages, and a conversation — behind a secret
 * link and a PIN (see App\Http\Controllers\RoomController).
 */
class ClientRoomResource extends Resource
{
    protected static ?string $model = ClientRoom::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $recordTitleAttribute = 'client_name';

    protected static string|UnitEnum|null $navigationGroup = 'Manage';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Client rooms';

    protected static ?string $modelLabel = 'client room';

    public static function form(Schema $schema): Schema
    {
        return ClientRoomForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientRoomsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DrawingsRelationManager::class,
            CommentsRelationManager::class,
        ];
    }

    /** Rooms where a client has commented or decided on a drawing since the studio last looked. */
    public static function getNavigationBadge(): ?string
    {
        $count = ClientRoom::query()->withUnseen()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClientRooms::route('/'),
            'create' => CreateClientRoom::route('/create'),
            'edit' => EditClientRoom::route('/{record}/edit'),
        ];
    }
}
