<?php

namespace App\Filament\Resources\Testimonials\Schemas;

use App\Models\Client;
use App\Models\Project;
use App\Models\Testimonial;
use App\Support\PortraitSketch;
use App\Support\ReadAloud;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        $rtl = ['dir' => 'rtl', 'lang' => 'ckb'];

        return $schema->components([
            Section::make('What they said')
                ->description('A quote shows only in the language it is written in — the client’s own words are never stood in for by a translation they did not give. Fill in one or both.')
                ->columns(2)
                ->schema([
                    Textarea::make('quote_en')->label('Quote (English)')->rows(5)
                        ->requiredWithout('quote_ku')->validationAttribute('quote'),
                    Textarea::make('quote_ku')->label('Quote (Kurdish)')->rows(5)
                        ->extraInputAttributes($rtl),
                    TextEntry::make('reading_status')->label('Read aloud')
                        ->visibleOn('edit')->columnSpanFull()
                        ->state(fn (Testimonial $record) => self::readingStatus($record))
                        ->hintAction(
                            Action::make('readNow')->label('Read the Kurdish now')->icon(Heroicon::OutlinedArrowPath)
                                ->visible(fn (Testimonial $record) => ReadAloud::kurdishReady() && filled($record->quote_ku) && $record->voiceUrl() === null)
                                ->action(function (Testimonial $record) {
                                    $error = $record->refreshReading();
                                    $error === null
                                        ? Notification::make()->success()->title('The Kurdish words are read')->send()
                                        : Notification::make()->danger()->title('They could not be read')->body($error)->persistent()->send();
                                }),
                        ),
                ]),

            Section::make('Who said it')
                ->columns(2)
                ->schema([
                    TextInput::make('person_name')->label('Name (English)')->required()->maxLength(120),
                    TextInput::make('person_name_ku')->label('Name (Kurdish)')->maxLength(120)
                        ->extraInputAttributes($rtl),
                    TextInput::make('person_role_en')->label('Role (English)')->maxLength(120)
                        ->placeholder('Owner · Managing Director · Homeowner'),
                    TextInput::make('person_role_ku')->label('Role (Kurdish)')->maxLength(120)
                        ->extraInputAttributes($rtl),
                    Select::make('client_id')->label('Client (optional)')
                        ->options(fn () => Client::query()->orderBy('sort_order')->pluck('name', 'id'))
                        ->searchable()->native(false)
                        ->helperText('Named after the role, e.g. “Owner, Empire World”.'),
                    Select::make('project_id')->label('About which project (optional)')
                        ->options(fn () => Project::query()->orderBy('sort_order')->pluck('name', 'id'))
                        ->searchable()->native(false)
                        ->helperText('The quote also appears on that project’s page.'),
                    Toggle::make('is_published')->label('Show on the website')->default(true),
                ]),

            Section::make('Their voice and portrait')
                ->description('Optional. Their voice note plays beside the quote, and their photo is turned into a pencil drawing that draws itself while they speak, inside a ring with their company’s name and logo (from the client chosen above). The photo itself is never shown on the website — only the drawing.')
                ->columns(3)->columnSpanFull()
                ->schema([
                    Toggle::make('media_consent')->label('They agreed to publish their voice and portrait')
                        ->helperText('Neither is shown on the website until this is on. Keep their “yes” in WhatsApp.')
                        ->columnSpanFull(),
                    FileUpload::make('voice')->label('Voice note')
                        ->disk('public')->directory('testimonials/voices')
                        ->acceptedFileTypes([
                            'audio/ogg', 'audio/opus', 'application/ogg', 'audio/mpeg', 'audio/mp3',
                            'audio/mp4', 'audio/x-m4a', 'audio/m4a', 'audio/aac', 'audio/x-aac',
                            'audio/wav', 'audio/x-wav', 'audio/wave', 'audio/webm',
                            // an .m4a is often taken for a video by its first bytes
                            'video/mp4',
                        ])
                        ->mimeTypeMap(['opus' => 'audio/ogg', 'm4a' => 'audio/mp4'])
                        ->maxSize(10240)
                        ->helperText('The file WhatsApp saves (.opus) plays on Android, computers and iPhones with iOS 18.4 or newer; older iPhones show the quote without it. An .m4a or .mp3 plays everywhere. The best 15–30 seconds work better than the whole message.'),
                    FileUpload::make('portrait')->label('Their photo')
                        ->disk(Testimonial::PHOTO_DISK)->directory('testimonials/photos')->visibility('private')
                        ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->imageEditor()->imageEditorAspectRatios(['1:1'])->imageCropAspectRatio('1:1')
                        // shrunk in the browser before it is sent: quicker on a phone, and a
                        // full-size phone photo is more than the drawing can open on the host
                        ->imageResizeMode('contain')->imageResizeTargetWidth('1200')->imageResizeTargetHeight('1200')
                        ->imageResizeUpscale(false)
                        ->maxSize(12288)
                        ->helperText('Crop it close around the face (the pencil icon). A well-lit photo against a plain wall draws best.'),
                    // the drawing beside the photo it was made from, with the pencil that drew it
                    Group::make([
                        TextEntry::make('sketch_preview')->label('The drawing')
                            ->visible(fn (?Testimonial $record) => filled($record?->portrait))
                            ->state(fn (Testimonial $record) => $record->portrait_sketch
                                ? new HtmlString('<img src="'.e(Storage::disk('public')->url($record->portrait_sketch)).'" alt="" width="200" height="200" style="border-radius:50%;background:#EFEBE3;box-shadow:0 0 0 1px rgba(0,0,0,.12)">')
                                : 'This photo could not be drawn — try another one (JPEG, PNG or WebP).'),
                        Select::make('sketch_detail')->label('Pencil')
                            ->options(PortraitSketch::details())->default('balanced')
                            ->selectablePlaceholder(false)->native(false)
                            ->helperText('Too faint, or too busy? Change this and save: it is drawn again.'),
                    ]),
                ]),
        ]);
    }

    /** How this quote is read aloud on the website, if it is: a line of text, or the Kurdish recording to hear. */
    private static function readingStatus(Testimonial $record): HtmlString|string
    {
        if ($record->voiceUrl() !== null) {
            return 'Not needed — their own voice note plays instead.';
        }
        if (! ReadAloud::enabled()) {
            return 'Switched off in Manage → Read aloud.';
        }
        $english = filled($record->quote_en) ? 'English: read by the visitor’s phone or computer.' : '';
        if (blank($record->quote_ku)) {
            return $english;
        }
        if ($url = $record->readingUrl()) {
            return new HtmlString(e($english).'<div style="margin-top:6px">Kurdish:</div>'
                .'<audio controls preload="none" style="width:100%;max-width:420px;margin-top:4px" src="'.e($url).'"></audio>');
        }

        return trim($english.' Kurdish: '.(ReadAloud::kurdishReady()
            ? 'not read yet — “Read the Kurdish now” shows why, if it cannot be.'
            : 'not read — add a kurdishtts.com key in Manage → Read aloud.'));
    }
}
