<?php

namespace App\Filament\Resources\WalkStops\Schemas;

use App\Filament\Forms\Components\MapPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WalkStopForm
{
    public static function configure(Schema $schema): Schema
    {
        $ku = ['dir' => 'rtl', 'lang' => 'ckb'];
        $ar = ['dir' => 'rtl', 'lang' => 'ar'];

        return $schema->components([
            Section::make('The place')->columns(3)->columnSpanFull()->schema([
                TextInput::make('name_en')->label('Name (English)')->required()->maxLength(160),
                TextInput::make('name_ku')->label('Name (Kurdish)')->maxLength(160)->extraInputAttributes($ku),
                TextInput::make('name_ar')->label('Name (Arabic)')->maxLength(160)->extraInputAttributes($ar),
                TextInput::make('built_en')->label('Built / date (English)')->maxLength(160)->placeholder('Late 12th century'),
                TextInput::make('built_ku')->label('Built / date (Kurdish)')->maxLength(160)->extraInputAttributes($ku),
                TextInput::make('built_ar')->label('Built / date (Arabic)')->maxLength(160)->extraInputAttributes($ar),
                Textarea::make('note_en')->label('Why it matters (English)')->rows(5)->helperText('2–4 sentences.'),
                Textarea::make('note_ku')->label('Why it matters (Kurdish)')->rows(5)->extraInputAttributes($ku),
                Textarea::make('note_ar')->label('Why it matters (Arabic)')->rows(5)->extraInputAttributes($ar),
            ]),
            Section::make('Where, and a photograph')->columns(2)->columnSpanFull()->schema([
                Hidden::make('lat'),
                Hidden::make('lng'),
                MapPicker::make('map_picker')->label('Pin on the map')
                    ->helperText('Drag the pin to the exact spot (or search a place name). The walk\'s route joins the stops in their order.')
                    ->columnSpanFull(),
                FileUpload::make('image')->label('Photograph')->image()
                    ->directory('walk')->disk('public')->visibility('public')->imagePreviewHeight('160')
                    ->helperText('Your own photograph, landscape. Until there is one, the page says "Photograph coming".'),
                TextInput::make('image_credit')->label('Photo credit (if not yours)')->maxLength(160),
                TextInput::make('sort_order')->label('Order on the walk')->numeric()->default(fn () => (int) \App\Models\WalkStop::max('sort_order') + 1),
                Toggle::make('is_published')->label('Shown on the walk')->default(true),
            ]),
        ]);
    }
}