<?php

namespace App\Filament\Resources\Products\Pages;

use App\Actions\Catalog\CopySupplierTree;
use App\Filament\Resources\Products\ProductResource;
use App\Models\PriceListSection;
use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->copyStructureAction(),
        ];
    }

    /**
     * Start a new supplier from one you have already built.
     *
     * Each supplier keeps their own tree, so a second crystal supplier would
     * otherwise begin by retyping Crystal, Flat Crystal and every shelf under
     * them. Two suppliers of the same goods stock the same shapes — what
     * differs is what they charge, and that is exactly what does not come
     * across.
     */
    private function copyStructureAction(): Action
    {
        return Action::make('copyStructure')
            ->label('Copy structure from a supplier')
            ->icon('heroicon-o-document-duplicate')
            ->color('gray')
            ->modalHeading('Copy a supplier\'s structure')
            ->modalDescription(
                'Copies the shelves, the products and their sizes — never the prices. '
                .'A price belongs to the supplier who quoted it.'
            )
            ->modalSubmitActionLabel('Copy structure')
            ->visible(fn () => auth()->user()?->can('manage_products') ?? false)
            ->schema([
                Select::make('price_list_section_id')
                    ->label('Price list')
                    ->options(fn () => PriceListSection::orderBy('sort_order')->pluck('name', 'id'))
                    ->required()
                    ->live(),

                Select::make('from_supplier_id')
                    ->label('Copy from')
                    ->required()
                    ->live()
                    // Only suppliers who have something in the chosen list;
                    // offering an empty one is offering to copy nothing.
                    ->options(fn (Get $get) => blank($get('price_list_section_id'))
                        ? []
                        : Supplier::query()
                            ->whereHas('products', fn (Builder $q) => $q
                                ->where('price_list_section_id', $get('price_list_section_id')))
                            ->orderBy('name')
                            ->pluck('name', 'id')),

                Select::make('to_supplier_id')
                    ->label('Copy to')
                    ->required()
                    ->options(fn (Get $get) => Supplier::query()
                        ->when($get('from_supplier_id'), fn (Builder $q, $from) => $q->whereKeyNot($from))
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->helperText('Anything this supplier already has in the list is left alone.'),

                Toggle::make('with_sizes')
                    ->label('Bring the sizes too')
                    ->default(true)
                    ->helperText('The size labels, with no prices against them.'),
            ])
            ->successNotificationTitle(null)
            ->action(function (array $data): void {
                $created = app(CopySupplierTree::class)->copy(
                    (int) $data['from_supplier_id'],
                    (int) $data['to_supplier_id'],
                    (int) $data['price_list_section_id'],
                    (bool) ($data['with_sizes'] ?? true),
                );

                Notification::make()
                    ->title($created > 0 ? 'Structure copied' : 'Nothing to copy')
                    ->body($created > 0
                        ? "{$created} ".str('product')->plural($created)
                            .' created, waiting for their prices.'
                        : 'That supplier has nothing in this price list.')
                    ->success()
                    ->send();
            });
    }
}
