<?php

namespace App\Filament\Resources\Essays\Schemas;

use App\Filament\Resources\Landmarks\Schemas\LandmarkForm;
use App\Models\Essay;
use Closure;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The admin's form for a journal essay: its title, line and cover for the
 * card, then the page as sections — the same kinds a heritage landmark's page
 * is made of, less the map, the UNESCO panel and related landmarks. Every
 * text three times: English, Kurdish, Arabic (the English stands in where a
 * translation is left empty).
 */
class EssayForm
{
    private const KU = ['dir' => 'rtl', 'lang' => 'ckb'];

    private const AR = ['dir' => 'rtl', 'lang' => 'ar'];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Essay')->persistTabInQueryString()->columnSpanFull()->tabs([
                Tab::make('Essay')->icon(Heroicon::OutlinedDocumentText)->schema([
                    Section::make('Title and line')->columns(3)->columnSpanFull()
                        ->description('What the card on /journal says, and the top of the page.')
                        ->schema([
                            TextInput::make('title_en')->label('Title (English)')->required()->maxLength(200),
                            TextInput::make('title_ku')->label('Title (Kurdish)')->maxLength(200)->extraInputAttributes(self::KU),
                            TextInput::make('title_ar')->label('Title (Arabic)')->maxLength(200)->extraInputAttributes(self::AR),
                            Textarea::make('summary_en')->label('One line (English)')->rows(3)->helperText('Under the title, and on the card.'),
                            Textarea::make('summary_ku')->label('One line (Kurdish)')->rows(3)->extraInputAttributes(self::KU),
                            Textarea::make('summary_ar')->label('One line (Arabic)')->rows(3)->extraInputAttributes(self::AR),
                        ]),
                    Section::make('Cover photograph')->columns(3)->columnSpanFull()->schema([
                        FileUpload::make('image')->label('Cover photograph')->image()
                            ->disk('public')->directory('journal')->visibility('public')->imagePreviewHeight('160')->maxSize(20480)
                            ->helperText('Landscape. The card on /journal, the top of the page and the link preview. Your own photograph, or one you may use — then write its credit.'),
                        TextInput::make('image_credit')->label('Photo credit')->maxLength(200),
                        TextInput::make('image_source')->label('Photo source link')->url()->maxLength(300),
                    ]),
                    Section::make('Publishing')->columns(3)->columnSpanFull()->schema([
                        TextInput::make('slug')->label('Web address')->prefix('/journal/')->maxLength(120)
                            ->rules(['nullable', 'regex:/^[a-z0-9-]+$/'])
                            ->rule(fn (?Essay $record) => function (string $attribute, $value, Closure $fail) use ($record) {
                                if (filled($value) && ! Essay::slugIsFree($value, $record?->id)) {
                                    $fail('Another page of the journal has this address.');
                                }
                            })
                            ->helperText('Small letters, digits and hyphens. Made from the English title when left empty.'),
                        DatePicker::make('date')->label('Date')->required()->default(now())->native(false),
                        Toggle::make('is_published')->label('Shown on the website')->default(false)->inline(false)
                            ->helperText('A draft is reachable only through Preview.'),
                    ]),
                ]),
                Tab::make('The page')->icon(Heroicon::OutlinedNewspaper)->schema([
                    Section::make('The page')->columnSpanFull()
                        ->description('Sections, in the order they are read: the same kinds as a heritage landmark\'s page. Text: a blank line between paragraphs, "- " for a list, "> " for a quotation, "## " for a small heading, **bold**, *italic*, [a link](https://…).')
                        ->schema([
                            Builder::make('sections')->label('')
                                ->blocks(LandmarkForm::blocks(Essay::BLOCKS))
                                ->collapsible()->collapsed(fn (?array $state) => filled($state))
                                ->cloneable()->reorderableWithButtons()->blockNumbers(false)
                                ->blockPickerColumns(2)->blockPickerWidth('2xl')
                                ->addActionLabel('Add a section')
                                ->columnSpanFull(),
                        ]),
                ]),
            ]),
        ]);
    }
}
