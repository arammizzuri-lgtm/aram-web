<?php

namespace App\Filament\Resources\Landmarks\Schemas;

use App\Filament\Forms\Components\MapPicker;
use App\Models\Landmark;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class LandmarkForm
{
    public static function configure(Schema $schema): Schema
    {
        $ku = ['dir' => 'rtl', 'lang' => 'ckb'];
        $ar = ['dir' => 'rtl', 'lang' => 'ar'];

        return $schema->components([
            Section::make('The landmark')->columns(3)->columnSpanFull()->schema([
                TextInput::make('name_en')->label('Name (English)')->required()->maxLength(160),
                TextInput::make('name_ku')->label('Name (Kurdish)')->maxLength(160)->extraInputAttributes($ku),
                TextInput::make('name_ar')->label('Name (Arabic)')->maxLength(160)->extraInputAttributes($ar),
                TextInput::make('place_en')->label('Place (English)')->maxLength(160)->placeholder('Near Riha (Şanlıurfa)'),
                TextInput::make('place_ku')->label('Place (Kurdish)')->maxLength(160)->extraInputAttributes($ku),
                TextInput::make('place_ar')->label('Place (Arabic)')->maxLength(160)->extraInputAttributes($ar),
                TextInput::make('period_en')->label('Period (English)')->maxLength(160)->placeholder('c. 9600–8200 BC'),
                TextInput::make('period_ku')->label('Period (Kurdish)')->maxLength(160)->extraInputAttributes($ku),
                TextInput::make('period_ar')->label('Period (Arabic)')->maxLength(160)->extraInputAttributes($ar),
                Select::make('region')->label('Part of Kurdistan')->options(Landmark::options(Landmark::REGIONS))->required()->native(false),
                Select::make('era')->label('Age')->options(Landmark::options(Landmark::ERAS))->required()->native(false),
                Toggle::make('unesco')->label('UNESCO World Heritage Site')->inline(false),
            ]),
            Section::make('Its story')->columns(3)->columnSpanFull()->schema([
                Textarea::make('summary_en')->label('One line (English)')->rows(3)->helperText('Shown on the card and on the map.'),
                Textarea::make('summary_ku')->label('One line (Kurdish)')->rows(3)->extraInputAttributes($ku),
                Textarea::make('summary_ar')->label('One line (Arabic)')->rows(3)->extraInputAttributes($ar),
                Textarea::make('body_en')->label('The page (English)')->rows(14)->helperText('Leave an empty line between paragraphs.'),
                Textarea::make('body_ku')->label('The page (Kurdish)')->rows(14)->extraInputAttributes($ku),
                Textarea::make('body_ar')->label('The page (Arabic)')->rows(14)->extraInputAttributes($ar),
            ]),
            Section::make('Where, and a photograph')->columns(2)->columnSpanFull()->schema([
                Hidden::make('lat'),
                Hidden::make('lng'),
                MapPicker::make('map_picker')->label('Pin on the map')
                    ->helperText('Drag the pin to the exact spot (or search a place name).')
                    ->columnSpanFull(),
                FileUpload::make('image')->label('Photograph')->image()
                    ->directory('heritage')->disk('public')->visibility('public')->imagePreviewHeight('160')
                    ->helperText('Landscape. Your own photograph, or one you have permission to use — then write its credit.'),
                TextInput::make('image_credit')->label('Photo credit (if not yours)')->maxLength(160),
                TextInput::make('slug')->label('Web address')->prefix('/kurdistan-heritage/')->maxLength(120)
                    ->rules(['nullable', 'alpha_dash'])->unique(ignoreRecord: true)
                    ->helperText('Made from the English name when left empty.'),
                TextInput::make('sort_order')->label('Order in the list')->numeric()->default(fn () => (int) Landmark::max('sort_order') + 1),
                Toggle::make('is_published')->label('Shown on the website')->default(true),
            ]),
        ]);
    }
}
