<?php

namespace App\Filament\Resources\Drawings\Schemas;

use App\Filament\Forms\Components\DrawingTracer;
use App\Models\Drawing;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * A drawing: its name, what it corrects (or nothing, for a new one), the survey's scan to trace over
 * (kept private: never on the site, only behind the admin login), and the tracer.
 */
class DrawingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Drawing')->columns(3)->columnSpanFull()->schema([
                TextInput::make('title_en')->label('Name')->required()->maxLength(200),
                TextInput::make('title_ku')->label('Name (Kurdish)')->maxLength(200)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ckb']),
                TextInput::make('title_ar')->label('Name (Arabic)')->maxLength(200)->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
                Select::make('base')->label('Corrects')->native(false)->searchable()->placeholder('Nothing — a new drawing')
                    ->options(fn () => collect(Drawing::options())->filter(fn ($v, $k) => str_starts_with($k, 'code:'))
                        ->mapWithKeys(fn ($v, $k) => [Str::after($k, 'code:') => $v])->all())
                    ->helperText('Pick one of the essays’ drawings to trace its walls again; leave empty to trace a new plan.')
                    ->live()->disabledOn('edit')->dehydrated(fn (string $operation) => $operation === 'create')->columnSpan(2),
                Toggle::make('is_live')->label('Live on the essay')->inline(false)
                    ->helperText('The traced walls replace the original’s on the essay. Its doors, windows and room names stay.')
                    ->visible(fn (Get $get) => filled($get('base'))),
                FileUpload::make('scan')->label('Survey scan (private)')
                    ->disk(Drawing::DISK)->directory('drawing-scans')->visibility('private')
                    ->image()->maxSize(30 * 1024)->previewable(false)->downloadable(false)->openable(false)
                    ->helperText('A picture of the survey to trace over. It is never shown on the site. Save after changing it.')
                    ->columnSpanFull(),
            ]),
            Section::make('Tracer')->columnSpanFull()
                ->description('Trace over the scan. Everything is kept with the Save button below.')
                ->hiddenOn('create')
                ->schema([DrawingTracer::make('data')->hiddenLabel()]),
        ]);
    }
}
