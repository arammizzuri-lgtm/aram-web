<?php

namespace App\Filament\Resources\CollectionPoints;

use App\Filament\Actions\RecordDeletion;
use App\Filament\Concerns\KeepsDeletedRecords;
use App\Filament\Resources\CollectionPoints\Pages\ManageCollectionPoints;
use App\Models\CollectionPoint;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Your forwarder's warehouses in China.
 *
 * These are addresses you hand to suppliers, not storage you control — nothing
 * of yours sits here in any sense worth tracking, so there are no quantities
 * and nothing to reconcile. The Chinese address is the field that matters: it
 * is what the supplier's driver actually reads.
 */
class CollectionPointResource extends Resource
{
    use KeepsDeletedRecords;

    protected static ?string $model = CollectionPoint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Collection points';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('city')->required()->maxLength(255),

                    Textarea::make('address')
                        ->label('Address (English)')
                        ->rows(2)
                        ->columnSpanFull(),

                    Textarea::make('address_zh')
                        ->label('Address 中文')
                        ->rows(2)
                        ->helperText('What you send the supplier. This is the one their driver reads.')
                        ->columnSpanFull(),

                    TextInput::make('contact_name')->label('Contact')->maxLength(255),
                    TextInput::make('phone')->tel()->maxLength(64),

                    Toggle::make('is_active')->label('In use')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No collection points yet')
            ->emptyStateDescription(
                'Your forwarder\'s warehouses in China. Add one and you can hand a supplier the exact delivery address, in Chinese.'
            )
            ->emptyStateIcon('heroicon-o-building-office-2')
            ->columns([
                TextColumn::make('city')->weight('medium')->sortable()->searchable(),
                TextColumn::make('name')->searchable(),

                TextColumn::make('address_zh')
                    ->label('Chinese address')
                    ->wrap()
                    ->placeholder('— not set')
                    // Without it you cannot tell a supplier where to deliver.
                    ->color(fn (?string $state) => $state ? null : 'warning'),

                TextColumn::make('contact_name')->label('Contact')->placeholder('—'),
                TextColumn::make('phone')->placeholder('—'),

                TextColumn::make('consignments_count')
                    ->label('Shipments')
                    ->counts('consignments')
                    ->alignEnd(),
            ])
            ->defaultSort('city')
            ->filters([RecordDeletion::filter()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCollectionPoints::route('/'),
        ];
    }
}
