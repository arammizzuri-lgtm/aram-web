<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Enums\LeadStatus;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListContactMessages extends ListRecords
{
    protected static string $resource = ContactMessageResource::class;

    // No "create" action — enquiries come only from the public form.
    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * The pipeline at a glance: what has just arrived, what is being worked
     * on, and what has been decided — each with its count.
     */
    public function getTabs(): array
    {
        $count = fn (array $statuses) => ContactMessage::query()
            ->whereIn('status', array_map(fn (LeadStatus $s) => $s->value, $statuses))->count() ?: null;
        $only = fn (array $statuses) => fn (Builder $query) => $query
            ->whereIn('status', array_map(fn (LeadStatus $s) => $s->value, $statuses));

        return [
            'all' => Tab::make('All'),
            'new' => Tab::make('New')
                ->icon(LeadStatus::New->getIcon())
                ->badge($count([LeadStatus::New]))->badgeColor('info')
                ->modifyQueryUsing($only([LeadStatus::New])),
            'open' => Tab::make('In progress')
                ->icon(LeadStatus::Contacted->getIcon())
                ->badge($count([LeadStatus::Contacted, LeadStatus::Quoted]))->badgeColor('warning')
                ->modifyQueryUsing($only([LeadStatus::Contacted, LeadStatus::Quoted])),
            'won' => Tab::make('Won')
                ->icon(LeadStatus::Won->getIcon())
                ->badge($count([LeadStatus::Won]))->badgeColor('success')
                ->modifyQueryUsing($only([LeadStatus::Won])),
            'lost' => Tab::make('Lost')
                ->icon(LeadStatus::Lost->getIcon())
                ->modifyQueryUsing($only([LeadStatus::Lost])),
        ];
    }
}
