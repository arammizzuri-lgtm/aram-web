<?php

namespace App\Filament\Actions;

use App\Models\Deal;
use App\Models\DealPurchase;
use App\Services\Deals\QuotationWriter;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Record that the customer said yes.
 *
 * Everything asked for is evidence: who told you, through which channel, and
 * what they said. None of it is inferred, because the value of the record is
 * precisely that it repeats what actually happened.
 *
 * An action class rather than a method on a page, because approval is now
 * reachable from two directions. It used to live only on the deal, hidden
 * unless a quotation existed — so the purchases screen could show three rows
 * bought at your own risk with no way to clear any of them without first
 * raising a document for a decision already made. Now it sits on the row that
 * is complaining.
 *
 * It takes either a deal or a purchase, and settles the deal either way:
 * approval belongs to the customer's request, not to one supplier's share of it,
 * so approving from one purchase clears every purchase on that deal at once.
 */
class RecordApproval extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'approve';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Record approval')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (?Model $record) => self::dealFrom($record)?->isApproved() === false)
            ->modalHeading(fn (?Model $record) => 'Approve '.(self::dealFrom($record)?->number ?? 'this deal'))
            ->modalDescription(fn (?Model $record) => self::description(self::dealFrom($record)))
            ->schema([
                TextInput::make('approved_by_name')
                    ->label('Who approved it?')
                    ->placeholder('The person at the customer who said yes')
                    ->required(),

                Select::make('approval_channel')
                    ->label('How?')
                    ->options([
                        'whatsapp' => 'WhatsApp',
                        'phone' => 'Phone call',
                        'in_person' => 'In person',
                        'email' => 'Email',
                        'viber' => 'Viber',
                    ])
                    ->native(false),

                Textarea::make('approval_note')
                    ->label('Anything they said')
                    ->placeholder('e.g. confirmed the gold finish, wants delivery before Eid')
                    ->rows(2),
            ])
            ->action(function (?Model $record, array $data) {
                $deal = self::dealFrom($record);

                if ($deal === null) {
                    Notification::make()->title('There is no deal to approve.')->danger()->send();

                    return;
                }

                try {
                    app(QuotationWriter::class)->recordApproval(
                        $deal,
                        $data['approved_by_name'],
                        $data['approval_channel'] ?? null,
                        $data['approval_note'] ?? null,
                    );
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title("{$deal->number} approved")
                    ->body('Nothing bought for this deal is at your own risk any more.')
                    ->success()
                    ->send();
            });
    }

    /** The deal, whether the screen handed us the deal or one of its purchases. */
    private static function dealFrom(?Model $record): ?Deal
    {
        return match (true) {
            $record instanceof Deal => $record,
            $record instanceof DealPurchase => $record->deal,
            default => null,
        };
    }

    /**
     * What approving actually settles, in money, for this deal.
     *
     * Named so it reads as a consequence rather than a status change: what is
     * being confirmed is that somebody is now committed to goods you have
     * already paid for.
     */
    private static function description(?Deal $deal): ?string
    {
        if ($deal === null) {
            return null;
        }

        $atRisk = $deal->purchases()->atRisk()->count();

        return trim(sprintf(
            '%s%s',
            $atRisk > 0
                ? sprintf(
                    'Clears the risk on %s bought for this deal. ',
                    $atRisk === 1 ? 'the purchase' : "all {$atRisk} purchases",
                )
                : '',
            $deal->customer?->name
                ? "Recorded against {$deal->customer->name}."
                : 'Recorded against this deal.',
        ));
    }
}
