<?php

namespace App\Filament\Resources\ContactMessages\Schemas;

use App\Enums\LeadStatus;
use App\Models\ContactMessage;
use App\Support\TrafficSource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * One enquiry: what the visitor wrote (read-only — it is their record, not
 * ours to edit) and, beside it, the studio's follow-up.
 */
class ContactMessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 3])->columnSpanFull()->schema([
                Section::make('Enquiry')
                    ->columnSpan(['lg' => 2])
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('created_at')->label('Received')
                            ->dateTime('j M Y, H:i', 'Asia/Baghdad')->suffix(' (Erbil)'),
                        TextEntry::make('phone')->placeholder('—')->copyable()
                            ->url(fn (ContactMessage $r) => $r->telUrl()),
                        TextEntry::make('email')->placeholder('—')->copyable()
                            ->url(fn (ContactMessage $r) => $r->email ? 'mailto:'.$r->email : null),
                        TextEntry::make('project_type')->label('Project type')->placeholder('—')
                            ->formatStateUsing(fn (ContactMessage $r) => $r->typeLabel()),
                        TextEntry::make('city')->placeholder('—'),
                        TextEntry::make('plot_area')->label('Plot area')->placeholder('—')
                            ->formatStateUsing(fn (ContactMessage $r) => $r->plotLabel()),
                        TextEntry::make('budget')->placeholder('—')
                            ->formatStateUsing(fn (ContactMessage $r) => $r->budgetLabel()),
                        TextEntry::make('timeline')->placeholder('—')
                            ->formatStateUsing(fn (ContactMessage $r) => $r->timelineLabel()),
                        TextEntry::make('project.name')->label('Was looking at')->placeholder('—')
                            ->url(fn (ContactMessage $r) => $r->project?->hasPage() ? $r->project->pageUrl($r->lang ?? 'en') : null, true),
                        TextEntry::make('source')->label('Came to the site from')->placeholder('—')
                            ->formatStateUsing(fn (?string $state) => TrafficSource::label($state)),
                        TextEntry::make('lang')->label('Wrote in')->placeholder('—')
                            ->formatStateUsing(fn (?string $state) => ['en' => 'English', 'ku' => 'Kurdish'][$state] ?? $state),
                        // line breaks kept, everything else escaped
                        TextEntry::make('message')->placeholder('No message')
                            ->formatStateUsing(fn (?string $state): string => nl2br(e((string) $state)))->html()
                            ->columnSpanFull()->extraAttributes(['dir' => 'auto']),
                    ]),

                Section::make('Follow-up')
                    ->description('Only the studio sees this.')
                    ->columnSpan(['lg' => 1])
                    ->schema([
                        Select::make('status')->options(LeadStatus::class)->native(false)->required()
                            ->selectablePlaceholder(false),
                        Textarea::make('notes')->rows(8)
                            ->placeholder('Calls, meetings, what was agreed, when to follow up…'),
                        Toggle::make('is_read')->label('Opened'),
                    ]),
            ]),
        ]);
    }
}
