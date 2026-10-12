<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Support\StudioMail;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use UnitEnum;

/**
 * Where the new-enquiry e-mails go, and the mailbox that sends them.
 *
 * Kept here rather than in .env because this host has no shell and caches
 * its configuration on deploy — see App\Support\StudioMail. The "Send a test
 * e-mail" button answers the only question that matters right away: does it
 * actually arrive?
 */
class EnquiryAlerts extends Page
{
    protected string $view = 'filament.pages.enquiry-alerts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'Manage';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Enquiry alerts';

    protected static ?string $title = 'Enquiry alerts';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'enabled' => StudioMail::enabled(),
            'to' => setting(StudioMail::TO),
            'host' => setting(StudioMail::HOST),
            'port' => setting(StudioMail::PORT) ?: '465',
            'username' => setting(StudioMail::USERNAME),
            'password' => '',
            'from' => setting(StudioMail::FROM),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $hasPassword = filled(setting(StudioMail::PASSWORD));

        return $schema->statePath('data')->components([
            Section::make('Who is told')
                ->description('Every enquiry from the website is e-mailed here, with buttons to reply, WhatsApp or call the person who wrote.')
                ->schema([
                    Toggle::make('enabled')->label('E-mail me when an enquiry arrives')->default(true),
                    TextInput::make('to')
                        ->label('Send alerts to')
                        ->placeholder(setting('contact_email_new', 'Info@arammizzuri.com'))
                        ->helperText('One address, or several separated by commas. Left empty, alerts go to the studio e-mail shown on the site.'),
                ]),

            Section::make('The mailbox that sends them')
                ->description('Create a mailbox for the website in cPanel → Email Accounts (for example website@yourdomain), then copy its details from "Connect Devices" → "Mail Client Manual Settings" → Outgoing Server. Until this is filled in, the server\'s default mail settings are used.')
                ->columns(2)
                ->schema([
                    TextInput::make('host')->label('Outgoing server (SMTP)')->placeholder('mail.yourdomain.com'),
                    Select::make('port')->label('Port')
                        ->options(['465' => '465 — SSL/TLS (recommended)', '587' => '587 — STARTTLS'])
                        ->native(false)->default('465')->selectablePlaceholder(false),
                    TextInput::make('username')->label('Username (the full mailbox address)')->email(),
                    TextInput::make('password')->label('Password')
                        ->password()->revealable()
                        ->placeholder($hasPassword ? '•••••••• saved — leave empty to keep' : '')
                        ->helperText('Stored encrypted.'),
                    TextInput::make('from')->label('Send as (optional)')->email()
                        ->placeholder('the mailbox address')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        SiteSetting::put(StudioMail::ENABLED, ! empty($state['enabled']) ? '1' : '0', 'alerts');
        foreach (['to' => StudioMail::TO, 'host' => StudioMail::HOST, 'port' => StudioMail::PORT,
            'username' => StudioMail::USERNAME, 'from' => StudioMail::FROM] as $field => $key) {
            SiteSetting::put($key, trim((string) ($state[$field] ?? '')), 'alerts');
        }
        if (filled($state['password'] ?? null)) {
            StudioMail::storePassword($state['password']);
        }
        SiteSetting::flushCache();

        $this->form->fill(array_merge($state, ['password' => '']));
        Notification::make()->success()->title('Alert settings saved')->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')
                ->label('Send a test e-mail')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(fn () => 'Uses the saved settings (save first if you have changed them) and sends to: '
                    .(implode(', ', StudioMail::recipients()) ?: 'nobody — no valid address'))
                ->action(function () {
                    $error = StudioMail::send(new class extends Mailable
                    {
                        public function envelope(): Envelope
                        {
                            return new Envelope(subject: 'Test — enquiry alerts from your website');
                        }

                        public function content(): Content
                        {
                            return new Content(htmlString: '<p style="font-family:Arial,sans-serif">This is a test from the website. '
                                .'If you are reading it, new enquiries will reach you here.</p>');
                        }
                    });

                    $error === null
                        ? Notification::make()->success()->title('Test e-mail sent')
                            ->body('Check the inbox (and the spam folder) of: '.implode(', ', StudioMail::recipients()))->send()
                        : Notification::make()->danger()->title('The test e-mail could not be sent')
                            ->body($error)->persistent()->send();
                }),
        ];
    }
}
