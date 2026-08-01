<?php

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Actions\Sales\ConfirmSalesOrder;
use App\Actions\Sales\CreditLimitExceeded;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Models\SalesOrder;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditSalesOrder extends EditRecord
{
    protected static string $resource = SalesOrderResource::class;

    protected function afterSave(): void
    {
        /** @var SalesOrder $record */
        $record = $this->record;
        $record->recalculateTotals();
        $record->forceFill(['base_total' => $record->fresh()->total])->saveQuietly();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirm')
                ->label('Confirm & reserve stock')
                ->icon('heroicon-m-check-circle')
                ->requiresConfirmation()
                ->modalDescription('Reserves the stock on this order so nobody else can sell it.')
                ->visible(fn (SalesOrder $record) => $record->status === 'draft')
                ->action(fn (SalesOrder $record) => $this->confirm($record, approved: false)),

            /*
             * A separate, permission-gated action rather than a checkbox on the
             * first one — approving credit is a manager's decision, and it needs
             * to be recorded as theirs.
             */
            Action::make('approveCredit')
                ->label('Approve credit & confirm')
                ->icon('heroicon-m-shield-check')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Approve beyond the credit limit')
                ->modalDescription(fn (SalesOrder $record) => sprintf(
                    '%s is already at $%s against a $%s limit. Approving records this against your name.',
                    $record->customer?->name,
                    number_format($record->customer?->outstandingBalance() ?? 0, 2),
                    number_format((float) ($record->customer?->credit_limit ?? 0), 2),
                ))
                ->visible(fn (SalesOrder $record) => $record->status === 'draft'
                    && $record->breachesCreditLimit()
                    && auth()->user()?->can('approve_credit'))
                ->action(fn (SalesOrder $record) => $this->confirm($record, approved: true)),

            Action::make('release')
                ->label('Release stock')
                ->icon('heroicon-m-lock-open')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (SalesOrder $record) => $record->is_reserved)
                ->action(function (SalesOrder $record) {
                    app(ConfirmSalesOrder::class)->release($record);

                    Notification::make()->title('Reservations released')->success()->send();
                    $this->refreshFormData(['is_reserved']);
                }),
        ];
    }

    private function confirm(SalesOrder $record, bool $approved): void
    {
        try {
            app(ConfirmSalesOrder::class)->handle($record, $approved);

            Notification::make()
                ->title('Order confirmed')
                ->body('Stock is reserved against this order.')
                ->success()
                ->send();

            $this->refreshFormData(['status', 'is_reserved']);
        } catch (CreditLimitExceeded $e) {
            // Recoverable by a manager, so it is offered rather than just refused.
            Notification::make()
                ->title('Over the credit limit')
                ->body($e->getMessage())
                ->warning()
                ->persistent()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Could not confirm this order')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
