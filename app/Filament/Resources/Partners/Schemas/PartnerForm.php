<?php

namespace App\Filament\Resources\Partners\Schemas;

use App\Models\Partner;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PartnerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('The partner')->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')->label('Name')->required()->maxLength(120),
                Select::make('category')->label('Trade')->options(Partner::categoryOptions())
                    ->default('other')->required()->native(false),
                TextInput::make('website')->label('Website')->maxLength(255)
                    ->placeholder('www.example.com')
                    ->helperText('Their own site — linked from the page (good for both of you on Google).'),
                FileUpload::make('logo')->label('Logo')->image()
                    ->directory('partners')->disk('public')->visibility('public')->imagePreviewHeight('80')
                    ->helperText('Ideally white or light on a transparent background (PNG or SVG).'),
            ]),
            Section::make('What they bring — one line')->columns(3)->columnSpanFull()->schema([
                TextInput::make('description_en')->label('English')->maxLength(200)
                    ->placeholder('Natural stone for façades and floors'),
                TextInput::make('description_ku')->label('Kurdish')->maxLength(200)
                    ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ckb']),
                TextInput::make('description_ar')->label('Arabic')->maxLength(200)
                    ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar']),
            ]),
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('sort_order')->label('Order')->numeric()->default(fn () => (int) Partner::max('sort_order') + 1),
                Toggle::make('is_published')->label('Shown on the website')->default(true),
            ]),
        ]);
    }
}