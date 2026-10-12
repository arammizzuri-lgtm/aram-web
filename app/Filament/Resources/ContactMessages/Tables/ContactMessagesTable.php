<?php

namespace App\Filament\Resources\ContactMessages\Tables;

use App\Enums\LeadStatus;
use App\Models\ContactMessage;
use App\Support\Enquiry;
use App\Support\TrafficSource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContactMessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('project'))
            // unread rows stand out until they have been opened
            ->recordClasses(fn (ContactMessage $r) => $r->is_read ? null : 'font-semibold')
            ->columns([
                IconColumn::make('is_read')->label('')
                    ->icon(fn (bool $state) => $state ? null : 'heroicon-s-sparkles')
                    ->color('info')->tooltip(fn (bool $state) => $state ? null : 'Not opened yet'),
                TextColumn::make('name')->searchable()->sortable()
                    ->description(fn (ContactMessage $r) => $r->phone ?: $r->email),
                // changed right here, without opening the enquiry
                SelectColumn::make('status')->options(LeadStatus::class)->selectablePlaceholder(false)
                    ->sortable()->width('11rem'),
                TextColumn::make('project_type')->label('Type')->badge()->color('gray')
                    ->formatStateUsing(fn (ContactMessage $r) => $r->typeLabel())->placeholder('—'),
                TextColumn::make('city')->placeholder('—')->toggleable(),
                TextColumn::make('budget')->formatStateUsing(fn (ContactMessage $r) => $r->budgetLabel())
                    ->placeholder('—')->toggleable(),
                TextColumn::make('timeline')->formatStateUsing(fn (ContactMessage $r) => $r->timelineLabel())
                    ->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('project.name')->label('Came from')->placeholder('—')
                    ->limit(28)->toggleable(),
                TextColumn::make('source')->label('Came from')->placeholder('—')
                    ->formatStateUsing(fn (?string $state) => TrafficSource::label($state))->toggleable(),
                TextColumn::make('message')->limit(50)->wrap()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Received')->since()->sortable()
                    ->dateTimeTooltip('j M Y, H:i', 'Asia/Baghdad'),
            ])
            ->filters([
                SelectFilter::make('project_type')->label('Type')
                    ->options(fn () => Enquiry::englishOptions(Enquiry::types())),
                SelectFilter::make('budget')->options(Enquiry::englishOptions(Enquiry::budgets())),
                SelectFilter::make('source')->label('Came from')->options(TrafficSource::labels()),
                SelectFilter::make('timeline')->options(Enquiry::englishOptions(Enquiry::timelines())),
                TernaryFilter::make('is_read')->label('Opened'),
            ])
            ->recordActions([
                EditAction::make()->label('Open'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
