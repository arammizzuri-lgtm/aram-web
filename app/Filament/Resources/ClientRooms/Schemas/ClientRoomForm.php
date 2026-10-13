<?php

namespace App\Filament\Resources\ClientRooms\Schemas;

use App\Models\ClientRoom;
use App\Models\RoomPayment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * One client room: whose it is and for which project, how they get in (the
 * link and PIN, once it exists), and the payment stages they are shown.
 * Drawings and the conversation sit below, as their own tables.
 */
class ClientRoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 3])->columnSpanFull()->schema([
                Section::make('Client')
                    ->columnSpan(['lg' => 2])
                    ->columns(2)
                    ->schema([
                        Select::make('project_id')->label('Project')
                            ->relationship('project', 'name')->searchable()->preload()->required()
                            ->helperText('Any project in the admin. It can stay unpublished, so a private house never shows on the site — only here.')
                            ->columnSpanFull(),
                        TextInput::make('client_name')->label('Client name')->required()->maxLength(120),
                        TextInput::make('client_phone')->label('Phone / WhatsApp')->tel()->maxLength(40)
                            ->helperText('To send them the link and the PIN on WhatsApp.'),
                        Select::make('lang')->label('Their language')
                            ->options(['ku' => 'Kurdish', 'en' => 'English'])->default('ku')
                            ->native(false)->selectablePlaceholder(false)->required()
                            ->helperText('The room opens in it; they can switch.'),
                        Select::make('currency')->label('Payments in')
                            ->options(ClientRoom::CURRENCIES)->default('USD')
                            ->native(false)->selectablePlaceholder(false)->required(),
                        Textarea::make('note')->label('Welcome note (optional)')->rows(3)
                            ->placeholder('A line at the top of their room, in their language.')
                            ->extraInputAttributes(['dir' => 'auto'])->columnSpanFull(),
                        Toggle::make('is_open')->label('Open')->default(true)
                            ->helperText('Closed, the link only says to contact the studio.')->columnSpanFull(),
                    ]),

                Section::make('How they get in')
                    ->description('Send the link and the PIN in two separate messages (the buttons at the top do that on WhatsApp).')
                    ->columnSpan(['lg' => 1])
                    ->visibleOn('edit')
                    ->schema([
                        TextEntry::make('room_link')->label('Link')
                            ->state(fn (ClientRoom $record) => $record->url())
                            ->copyable()->url(fn (ClientRoom $record) => $record->url(), true)
                            ->extraAttributes(['style' => 'word-break: break-all']),
                        TextEntry::make('room_pin')->label('PIN')
                            ->state(fn (ClientRoom $record) => $record->pin)
                            ->copyable()->size('lg')->weight('bold')->fontFamily('mono'),
                        TextEntry::make('last_seen_at')->label('Last opened by the client')
                            ->since()->placeholder('Not yet'),
                    ]),

                Section::make('Payment stages')
                    ->description('What the client sees under Payments: each stage, what it comes to, and whether it is paid. Nothing is paid through the site.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('payments')->relationship()->label('')
                            ->orderColumn('sort_order')
                            ->columns(['default' => 1, 'md' => 4])
                            ->schema([
                                TextInput::make('label')->label('Stage')->required()->maxLength(160)
                                    ->placeholder('Concept design')->columnSpan(['md' => 2])
                                    ->extraInputAttributes(['dir' => 'auto']),
                                TextInput::make('amount')->numeric()->minValue(0)->required(),
                                DatePicker::make('due_on')->label('Due (optional)')->native(false)->displayFormat('j M Y'),
                                DatePicker::make('paid_on')->label('Paid on')->native(false)->displayFormat('j M Y')
                                    ->helperText('Empty until it is paid.'),
                                FileUpload::make('receipt_path')->label('Receipt (optional) — PDF or photo')
                                    ->disk(RoomPayment::DISK)->directory('rooms/receipts')->visibility('private')
                                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                                    ->maxSize(10240)->storeFileNamesIn('receipt_name')
                                    ->columnSpan(['md' => 3]),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->addActionLabel('Add a payment stage')
                            ->collapsible()
                            ->defaultItems(0),
                    ]),
            ]),
        ]);
    }
}
