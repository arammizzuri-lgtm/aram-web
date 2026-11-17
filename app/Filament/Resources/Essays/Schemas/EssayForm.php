<?php

namespace App\Filament\Resources\Essays\Schemas;

use App\Filament\Resources\Landmarks\Schemas\LandmarkForm;
use App\Models\Drawing;
use App\Models\Essay;
use Closure;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The admin's form for a journal essay: its title, line and cover for the
 * card, then the page as sections — the same kinds a heritage landmark's page
 * is made of, less the map, the UNESCO panel and related landmarks, and for an
 * essay on buildings the journal's own: figures at the top, chapters, plates
 * (blocks()). Every text three times: English, Kurdish, Arabic (the English
 * stands in where a translation is left empty).
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
                    Section::make('Drawing sheet on the card')->columnSpanFull()->collapsible()->collapsed()
                        ->description('For an essay with plates and no cover photograph: its card on /journal shows a plan and a section from its own drawings, with a title block.')
                        ->schema([
                            Grid::make(2)->schema([
                                Select::make('extras.cover_plan')->label('Plan')->options(fn () => Drawing::options())->searchable(),
                                Select::make('extras.cover_section')->label('Section')->options(fn () => Drawing::options())->searchable(),
                            ]),
                            LandmarkForm::tri('extras.kicker', 'Small line', help: 'In capitals, e.g. ERBIL CITADEL'),
                            LandmarkForm::tri('extras.line1', 'Title, first line'),
                            LandmarkForm::tri('extras.line2', 'Title, second line'),
                            LandmarkForm::tri('extras.note', 'Count', help: 'e.g. 12 PLANS · 5 SECTIONS'),
                            TextInput::make('extras.code')->label('Sheet code')->maxLength(40)->placeholder('CH-01 — CH-08'),
                        ]),
                    Section::make('At the end, and for search engines')->columnSpanFull()->collapsible()->collapsed()->schema([
                        LandmarkForm::tri('extras.link', 'A button at the end'),
                        Grid::make(2)->schema([
                            TextInput::make('extras.link_url')->label('Where it leads')->maxLength(300)
                                ->helperText('A page of this site from its first slash (/kurdistan-heritage), kept in the reader\'s language, or a full https:// link.'),
                            TextInput::make('extras.about')->label('The building or place it is about')->maxLength(200)->placeholder('Erbil Citadel'),
                        ]),
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
                        ->description('Sections, in the order they are read: the same kinds as a heritage landmark\'s page, and the journal\'s own for an essay on buildings (figures at the top, chapters, plates). Text: a blank line between paragraphs, "- " for a list, "> " for a quotation, "## " for a small heading, **bold**, *italic*, [a link](https://…).')
                        ->schema([
                            Builder::make('sections')->label('')
                                ->blocks([...LandmarkForm::blocks(Essay::BLOCKS), ...self::blocks()])
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

    /**
     * The journal's own sections, for an essay on buildings (heritage/blocks: count, chapter, plates):
     * figures at the top, chapters made of parts, and plates with their drawings, legend and facts.
     */
    public static function blocks(): array
    {
        $tri = [LandmarkForm::class, 'tri'];
        $list = fn (string $name, string $label, array $schema, string $add, string $title = 'title') => Repeater::make($name)->label($label)->schema($schema)
            ->itemLabel(fn (array $state) => LandmarkForm::words($state[$title.'_en'] ?? '', 6) ?: null)
            ->reorderable()->collapsible()->collapsed()->cloneable()->defaultItems(0)->addActionLabel($add);
        $drawings = fn (string $name, string $label, bool $plans) => $list($name, $label, [
            Select::make('drawing')->label('Drawing')->options(fn () => Drawing::options())->searchable()->required()
                ->helperText('The essays\' own drawings, or a plan traced in Portfolio → Drawings.'),
            $tri('caption', 'Caption under it', help: $plans ? 'e.g. "Ground floor"' : 'e.g. "Long section"'),
            ...($plans ? [Toggle::make('wide')->label('Wide: the whole width of the plate')] : []),
        ], 'Add a drawing', 'caption');

        return [
            Block::make('count')->label('Figures at the top')->icon(Heroicon::OutlinedHashtag)->schema([
                $list('items', 'Figures', [$tri('label', 'Word'), TextInput::make('value')->label('Figure')->required()->maxLength(40)->placeholder('12')], 'Add a figure', 'label'),
            ]),

            Block::make('chapter')->label(fn (?array $state) => 'Chapter'.(filled($state['heading_en'] ?? null) ? ' · '.$state['heading_en'] : ''))
                ->icon(Heroicon::OutlinedBookOpen)->schema([
                    Select::make('style')->label('Kind')->default('text')->native(false)
                        ->options(['text' => 'Text', 'sources' => 'Sources: the reading list, left to right']),
                    $tri('heading', 'Heading'),
                    Builder::make('parts')->label('Parts, in the order they are read')->blocks([
                        Block::make('paragraphs')->label(fn (?array $state) => 'Paragraphs · '.LandmarkForm::words($state['text_en'] ?? ''))
                            ->schema([$tri('text', 'Paragraphs', true, 8, 'A blank line between paragraphs; **bold**, *italic*, [a link](https://…).')]),
                        Block::make('note')->label(fn (?array $state) => 'Note · '.LandmarkForm::words($state['text_en'] ?? ''))
                            ->schema([$tri('text', 'Note, on a gold rule', true, 4)]),
                        Block::make('quote')->label('Quotation')->schema([$tri('text', 'Quotation', true, 3), $tri('cite', 'Whose words')]),
                        Block::make('list')->label(fn (?array $state) => 'List · '.count($state['items'] ?? []))->schema([
                            Select::make('style')->label('Kind')->default('terms')->native(false)
                                ->options(['terms' => 'Terms: a word and what it means', 'build' => 'How it is built: a part and how it is made']),
                            $list('items', 'Items', [
                                $tri('term', 'Word'),
                                $tri('text', 'What it means', true, 3),
                                TextInput::make('anchor')->label('Its entry in the glossary')->prefix('/journal/glossary#')->maxLength(60)
                                    ->rules(['nullable', 'regex:/^[a-z0-9-]+$/'])->helperText('Optional: the word links there, e.g. iwan'),
                            ], 'Add an item', 'term'),
                        ]),
                        Block::make('periods')->label('Periods, with their dates')->schema([
                            $list('items', 'Periods', [
                                TextInput::make('when')->label('Dates')->maxLength(60)->placeholder('c. 1880 – 1918'),
                                $tri('title', 'Name'),
                                $tri('text', 'What marks it', true, 3),
                            ], 'Add a period'),
                        ]),
                        Block::make('diagrams')->label('The courtyard house diagrams')->schema([
                            // the shapes are drawn in code (journal/type); their words are here
                            $list('items', 'Diagrams', [Hidden::make('tp'), $tri('title', 'Name'), $tri('text', 'Line under it')], 'Add')
                                ->addable(false)->cloneable(false),
                        ]),
                        Block::make('caption')->label('Small caption')->schema([$tri('text', 'Caption')]),
                        Block::make('glossary')->label('Link to the glossary')->schema([
                            $tri('text', 'Link text'),
                            TextInput::make('anchor')->label('Entry')->prefix('/journal/glossary#')->required()->maxLength(60)->rules(['regex:/^[a-z0-9-]+$/']),
                        ]),
                        Block::make('refs')->label(fn (?array $state) => 'Sources · '.count($state['items'] ?? []))->schema([
                            Repeater::make('items')->label('Sources')->schema([
                                Textarea::make('text')->label('Source')->rows(2)->required()
                                    ->helperText('Authors (year), ‘Title’, *Journal or book* volume, pages. [doi.org/…](https://doi.org/…)'),
                            ])->reorderable()->defaultItems(0)->addActionLabel('Add a source'),
                        ]),
                    ])->collapsible()->collapsed(fn (?array $state) => filled($state))->reorderableWithButtons()->cloneable()->blockNumbers(false)
                        ->blockPickerColumns(2)->addActionLabel('Add a part'),
                ]),

            Block::make('plates')->label(fn (?array $state) => 'Plates · '.count($state['plates'] ?? []).(filled($state['heading_en'] ?? null) ? ' · '.$state['heading_en'] : ''))
                ->icon(Heroicon::OutlinedRectangleGroup)->schema([
                    $tri('heading', 'Heading'),
                    $tri('key', 'A word on the plates', true, 3),
                    TextInput::make('code')->label('Plate code')->default('PL')->required()->maxLength(6)->rules(['regex:/^[A-Z0-9]+$/'])
                        ->helperText('Capitals: CH numbers the plates CH-01, CH-02 …'),
                    $list('plates', 'Plates', [
                        $tri('name', 'Name'),
                        $tri('type', 'Type, the small line beside it'),
                        $drawings('plans', 'Plans, side by side at one scale', true),
                        $drawings('secs', 'Sections and elevations, below them', false),
                        $tri('text', 'Text', true, 6, 'A blank line between paragraphs.'),
                        $list('legend', 'Legend: the numbers on the drawings', [
                            TextInput::make('n')->label('Number')->required()->maxLength(8)->placeholder('1–3'),
                            $tri('label', 'What it is'),
                        ], 'Add a number', 'label'),
                        $list('facts', 'Facts', [
                            $tri('label', 'Fact'),
                            $tri('value', 'Value', help: 'A number or a code (19/2) only in English is enough: the English stands in.'),
                            Toggle::make('ltr')->label('A number or a code: set left to right'),
                        ], 'Add a fact', 'label'),
                    ], 'Add a plate', 'name'),
                ]),
        ];
    }
}
