<?php

namespace App\Filament\Resources\JobApplications\Schemas;

use App\Enums\ApplicationStatus;
use App\Models\JobApplication;
use App\Support\TrafficSource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** One application: what the applicant sent (read-only), and the studio's follow-up. */
class JobApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 3])->columnSpanFull()->schema([
                Section::make('Application')
                    ->columnSpan(['lg' => 2])
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('created_at')->label('Received')
                            ->dateTime('j M Y, H:i', 'Asia/Baghdad')->suffix(' (Erbil)'),
                        TextEntry::make('position')->label('Position')
                            ->state(fn (JobApplication $r) => $r->positionLabel()),
                        TextEntry::make('cv_name')->label('CV')
                            ->formatStateUsing(fn (JobApplication $r) => $r->cv_name.' · '.$r->cvSizeLabel())
                            ->url(fn (JobApplication $r) => $r->cvExists() ? route('filament.studio-aks-panel.application-cv', $r) : null, true)
                            ->color('primary'),
                        TextEntry::make('email')->copyable()->url(fn (JobApplication $r) => 'mailto:'.$r->email),
                        TextEntry::make('phone')->placeholder('—')->copyable(),
                        TextEntry::make('portfolio_url')->label('Portfolio')->placeholder('—')
                            ->url(fn (JobApplication $r) => $r->portfolio_url, true)->limit(60),
                        TextEntry::make('source')->label('Came to the site from')->placeholder('—')
                            ->formatStateUsing(fn (?string $state) => TrafficSource::label($state)),
                        TextEntry::make('message')->placeholder('No message')
                            ->formatStateUsing(fn (?string $state): string => nl2br(e((string) $state)))->html()
                            ->columnSpanFull()->extraAttributes(['dir' => 'auto']),
                    ]),

                Section::make('Follow-up')
                    ->description('Only the studio sees this.')
                    ->columnSpan(['lg' => 1])
                    ->schema([
                        Select::make('status')->options(ApplicationStatus::class)->native(false)->required()
                            ->selectablePlaceholder(false),
                        Textarea::make('notes')->rows(8)->placeholder('Interview notes, impressions, next step…'),
                        Toggle::make('is_read')->label('Opened'),
                    ]),
            ]),
        ]);
    }
}
