<?php

namespace App\Filament\Resources\Partners\Schemas;

use App\Filament\Forms\Components\MapPicker;
use App\Models\Partner;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class PartnerForm
{
    public static function configure(Schema $schema): Schema
    {
        $ku = ['dir' => 'rtl', 'lang' => 'ckb'];
        $ar = ['dir' => 'rtl', 'lang' => 'ar'];

        return $schema->components([
            // an application from the website: who sent it and what they wrote (never shown publicly)
            Section::make('Application from the website')->columns(3)->columnSpanFull()
                ->description('Sent with the "Become a partner" form. Check the details below, then switch on "Shown on the website" at the bottom to publish it.')
                ->visible(fn ($record) => $record && ($record->is_application || filled($record->contact_person) || filled($record->email)))
                ->schema([
                    TextInput::make('contact_person')->label('Contact person')->maxLength(120),
                    TextInput::make('email')->label('Email')->email()->maxLength(190),
                    TextInput::make('applied_at')->label('Applied')->disabled()->dehydrated(false)
                        ->formatStateUsing(fn ($state) => $state ? \Illuminate\Support\Carbon::parse($state)->timezone('Asia/Baghdad')->format('j M Y, H:i') : '—'),
                    Textarea::make('message')->label('Their message')->rows(4)->columnSpanFull(),
                ]),
            Section::make('The partner')->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')->label('Name')->required()->maxLength(120),
                Select::make('category')->label('Trade')->options(Partner::categoryOptions())
                    ->default('other')->required()->native(false),
                TextInput::make('website')->label('Website')->maxLength(255)
                    ->placeholder('www.example.com')
                    ->helperText('Their own site — linked from the page (good for both of you on Google).'),
                FileUpload::make('logo')->label('Logo')->image()
                    ->directory('partners')->disk('public')->visibility('public')->imagePreviewHeight('80')
                    ->helperText('Any logo file. A white one-colour version is made for the logo wall automatically; the original shows when a visitor opens the partner.'),
            ]),
            Section::make('What they bring — one line')->columns(3)->columnSpanFull()->schema([
                TextInput::make('description_en')->label('English')->maxLength(200)
                    ->placeholder('Natural stone for façades and floors'),
                TextInput::make('description_ku')->label('Kurdish')->maxLength(200)->extraInputAttributes($ku),
                TextInput::make('description_ar')->label('Arabic')->maxLength(200)->extraInputAttributes($ar),
            ]),
            Section::make('Their words')->columns(3)->columnSpanFull()->collapsible()
                ->description('Optional. A sentence from the partner about working with the studio. When empty, nothing is shown.')
                ->schema([
                    Textarea::make('quote_en')->label('Quote (English)')->rows(3),
                    Textarea::make('quote_ku')->label('Quote (Kurdish)')->rows(3)->extraInputAttributes($ku),
                    Textarea::make('quote_ar')->label('Quote (Arabic)')->rows(3)->extraInputAttributes($ar),
                    TextInput::make('quote_by')->label('Who said it')->maxLength(120)->placeholder('Karwan Ali'),
                    TextInput::make('quote_role_en')->label('Their role (English)')->maxLength(160)->placeholder('Sales Director'),
                    TextInput::make('quote_role_ku')->label('Their role (Kurdish)')->maxLength(160)->extraInputAttributes($ku),
                    TextInput::make('quote_role_ar')->label('Their role (Arabic)')->maxLength(160)->extraInputAttributes($ar),
                ]),
            Section::make('Projects together')->columnSpanFull()
                ->description('The studio projects this partner worked on. They show, with photos, when a visitor opens the partner.')
                ->schema([
                    Select::make('projects')->label('Projects')->relationship('projects', 'name')
                        ->multiple()->preload()->searchable(),
                ]),
            Section::make('Contact & location')->columns(3)->columnSpanFull()
                ->description('All optional. Shown when a visitor opens the partner, so clients can call or visit the showroom.')
                ->schema([
                    TextInput::make('phone')->label('Phone')->tel()->maxLength(40)->placeholder('+964 750 000 0000'),
                    Toggle::make('whatsapp')->label('This number is on WhatsApp')->inline(false),
                    TextInput::make('address_en')->label('Address (English)')->maxLength(255)->placeholder('100m Street, Erbil'),
                    TextInput::make('address_ku')->label('Address (Kurdish)')->maxLength(255)->extraInputAttributes($ku),
                    TextInput::make('address_ar')->label('Address (Arabic)')->maxLength(255)->extraInputAttributes($ar),
                    Hidden::make('lat'),
                    Hidden::make('lng'),
                    MapPicker::make('map_picker')->label('Showroom on the map')
                        ->helperText('Drag the pin to their showroom or office (or search a place name). Leave it if they have none.')
                        ->columnSpanFull(),
                ]),
            Section::make('Website badge')->columnSpanFull()->collapsible()
                ->description('An "Official partner" badge for the partner\'s own website — it links to their page here, and every visit through it is counted.')
                ->visible(fn ($record) => (bool) $record?->exists)
                ->schema([View::make('filament.partners.badge')]),
            Section::make('Statistics')->columnSpanFull()->collapsible()
                ->description('What visitors did with this partner on your website.')
                ->visible(fn ($record) => (bool) $record?->exists)
                ->schema([View::make('filament.partners.stats')]),
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('sort_order')->label('Order')->numeric()->default(fn () => (int) Partner::max('sort_order') + 1),
                Toggle::make('is_published')->label('Shown on the website')->default(true),
            ]),
        ]);
    }
}
