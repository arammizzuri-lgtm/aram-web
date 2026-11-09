<?php

namespace App\Filament\Resources\Partners\Tables;

use App\Models\Partner;
use App\Models\PartnerEvent;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PartnersTable
{
    public static function configure(Table $table): Table
    {
        // the last 30 days for every partner, in one query
        $counts = null;
        $last30 = function (Partner $r) use (&$counts): string {
            $counts ??= PartnerEvent::countsSince(now()->subDays(30));
            $c = $counts[$r->id] ?? [];
            $opens = $c['open'] ?? 0;
            $reached = collect(PartnerEvent::CONTACTS)->sum(fn ($t) => $c[$t] ?? 0);

            return $opens || $reached ? "{$opens} opened · {$reached} reached them" : '—';
        };

        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('logo')->label('')->disk('public')->height(36),
                TextColumn::make('name')->searchable()->sortable()->description(fn (Partner $r) => $r->description_en ?: $r->description_ku ?: $r->description_ar),
                TextColumn::make('category')->label('Trade')->badge()->formatStateUsing(fn ($state) => Partner::categoryName((string) $state, 'en')),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (Partner $r) => $r->is_application ? 'Application' : ($r->is_published ? 'Shown' : 'Hidden'))
                    ->color(fn (string $state) => match ($state) { 'Application' => 'warning', 'Shown' => 'success', default => 'gray' }),
                TextColumn::make('last30')->label('Last 30 days')->state($last30)->color('gray'),
            ])
            ->filters([
                TernaryFilter::make('is_application')->label('Applications')
                    ->trueLabel('Applications only')->falseLabel('Partners only'),
                SelectFilter::make('category')->label('Trade')->options(Partner::categoryOptions()),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('No partners yet')
            ->emptyStateDescription('Add the suppliers, brands and contractors you build with — or wait for applications from the "Become a partner" form. The Partners page and the homepage section appear once the first partner is shown.');
    }
}
