<?php

namespace App\Filament\Resources\VisitRequests;

use App\Filament\Resources\VisitRequests\Pages\ListVisitRequests;
use App\Models\VisitRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Visits asked for on the website (/visit). Each waits here: Confirm keeps
 * the day and time they asked for (or the one suggested, once they agree);
 * "Suggest another time" picks a new one. Either way the WhatsApp message
 * is then ready, in their language.
 */
class VisitRequestResource extends Resource
{
    protected static ?string $model = VisitRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Clients';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Visits';

    protected static ?string $modelLabel = 'visit';

    public static function table(Table $table): Table
    {
        $when = fn (VisitRequest $r) => $r->day()->format('D j M').', '.$r->time();

        return $table
            ->defaultSort('created_at', 'desc')
            ->recordClasses(fn (VisitRequest $r) => $r->status === VisitRequest::WAITING ? 'font-semibold' : null)
            ->columns([
                TextColumn::make('name')->searchable()->wrap()->description(fn (VisitRequest $r) => $r->phone),
                TextColumn::make('kind')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state) => VisitRequest::KINDS[$state] ?? $state),
                TextColumn::make('wanted_on')->label('Asked for')->date('D j M Y')->sortable()
                    ->description(fn (VisitRequest $r) => $r->wanted_time),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state) => match ($state) {
                        VisitRequest::CONFIRMED => 'success',
                        VisitRequest::SUGGESTED => 'info',
                        default => 'warning',
                    })
                    ->description(fn (VisitRequest $r) => $r->status === VisitRequest::WAITING ? null
                        : ($r->status === VisitRequest::SUGGESTED ? 'Suggested ' : '').$when($r)),
                TextColumn::make('about')->limit(60)->wrap()->visibleFrom('md'),
                TextColumn::make('created_at')->label('Received')->since()->sortable()
                    ->dateTimeTooltip('j M Y, H:i', 'Asia/Baghdad'),
            ])
            ->recordActions([
                Action::make('confirm')->label('Confirm')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (VisitRequest $r) => $r->status !== VisitRequest::CONFIRMED)
                    ->requiresConfirmation()
                    ->modalDescription(fn (VisitRequest $r) => $when($r).($r->status === VisitRequest::SUGGESTED ? ' — the time you suggested.' : ' — as they asked.'))
                    ->action(fn (VisitRequest $r) => self::settle($r, VisitRequest::CONFIRMED, $r->day()->toDateString(), $r->time())),
                Action::make('suggest')->label('Suggest another time')->icon(Heroicon::OutlinedClock)
                    ->visible(fn (VisitRequest $r) => $r->status !== VisitRequest::CONFIRMED)
                    ->fillForm(fn (VisitRequest $r) => ['set_on' => $r->day()->toDateString(), 'set_time' => $r->time()])
                    ->schema([
                        DatePicker::make('set_on')->label('Day')->required()->minDate(now('Asia/Baghdad')->startOfDay()),
                        TimePicker::make('set_time')->label('Time')->required()->seconds(false)->format('H:i'),
                    ])
                    ->action(fn (VisitRequest $r, array $data) => self::settle($r, VisitRequest::SUGGESTED, $data['set_on'], $data['set_time'])),
                Action::make('whatsapp')->label('WhatsApp')->icon(Heroicon::OutlinedChatBubbleLeftRight)->color('success')
                    ->visible(fn (VisitRequest $r) => $r->status !== VisitRequest::WAITING && filled($r->whatsappUrl()))
                    ->url(fn (VisitRequest $r) => $r->whatsappUrl(), shouldOpenInNewTab: true),
                DeleteAction::make(),
            ]);
    }

    /** Confirm it or suggest another time, then hand over the WhatsApp message. */
    private static function settle(VisitRequest $r, string $status, string $day, string $time): void
    {
        $r->update(['status' => $status, 'set_on' => $day, 'set_time' => substr($time, 0, 5)]);
        $url = $r->whatsappUrl();

        Notification::make()->success()->persistent()
            ->title($status === VisitRequest::CONFIRMED ? 'Confirmed' : 'Another time suggested')
            ->body($url ? 'Now send it to '.$r->name.' on WhatsApp — the message is ready in their language.'
                : 'Their number does not look like a phone number: call or message '.$r->phone.' yourself.')
            ->actions($url ? [Action::make('send')->label('Send on WhatsApp')->button()->url($url, shouldOpenInNewTab: true)] : [])
            ->send();
    }

    /** Visits are only asked for on the website. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** How many wait for an answer. */
    public static function getNavigationBadge(): ?string
    {
        return (string) VisitRequest::query()->where('status', VisitRequest::WAITING)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVisitRequests::route('/'),
        ];
    }
}
