<?php

namespace App\Filament\Resources\JobApplications\Tables;

use App\Enums\ApplicationStatus;
use App\Models\JobApplication;
use App\Models\JobOpening;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JobApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('opening'))
            ->recordClasses(fn (JobApplication $r) => $r->is_read ? null : 'font-semibold')
            ->columns([
                IconColumn::make('is_read')->label('')
                    ->icon(fn (bool $state) => $state ? null : 'heroicon-s-sparkles')
                    ->color('info')->tooltip(fn (bool $state) => $state ? null : 'Not opened yet'),
                TextColumn::make('name')->searchable()->sortable()->description(fn (JobApplication $r) => $r->email),
                TextColumn::make('position')->label('Position')
                    ->state(fn (JobApplication $r) => $r->positionLabel())->badge()
                    ->color(fn (JobApplication $r) => $r->opening ? 'primary' : 'gray'),
                SelectColumn::make('status')->options(ApplicationStatus::class)->selectablePlaceholder(false)
                    ->width('10rem'),
                TextColumn::make('created_at')->label('Received')->since()->sortable()
                    ->dateTimeTooltip('j M Y, H:i', 'Asia/Baghdad'),
            ])
            ->filters([
                SelectFilter::make('job_opening_id')->label('Position')
                    ->options(fn () => JobOpening::query()->orderBy('sort_order')->pluck('title_en', 'id')),
                SelectFilter::make('status')->options(ApplicationStatus::class),
            ])
            ->recordActions([
                Action::make('cv')->label('CV')->icon(Heroicon::OutlinedDocumentText)
                    ->url(fn (JobApplication $r) => route('filament.studio-aks-panel.application-cv', $r), shouldOpenInNewTab: true)
                    ->visible(fn (JobApplication $r) => $r->cvExists()),
                EditAction::make()->label('Open'),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No applications yet')
            ->emptyStateDescription('Applications from the careers page appear here, each with its CV.');
    }
}
