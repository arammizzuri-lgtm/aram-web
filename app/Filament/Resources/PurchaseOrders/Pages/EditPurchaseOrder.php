<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Actions\Purchasing\ConfirmPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function afterSave(): void
    {
        /** @var PurchaseOrder $record */
        $record = $this->record;
        $record->recalculateTotals();
        $record->forceFill(['base_total' => $record->fresh()->total])->saveQuietly();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirm')
                ->label('Confirm order')
                ->icon('heroicon-m-check-circle')
                ->requiresConfirmation()
                ->modalDescription(fn (PurchaseOrder $record) => sprintf(
                    'Books %s as incoming stock, so nobody reorders what is already on its way. Deposit is %s%%.',
                    number_format((float) $record->items()->sum('quantity'), 0).' pieces',
                    $record->deposit_percent,
                ))
                ->visible(fn (PurchaseOrder $record) => ! $record->status->isCommitted())
                ->action(function (PurchaseOrder $record) {
                    try {
                        app(ConfirmPurchaseOrder::class)->handle($record);

                        Notification::make()
                            ->title('Purchase order confirmed')
                            ->body('Quantities are now showing as incoming stock.')
                            ->success()
                            ->send();

                        $this->refreshFormData(['status']);
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Could not confirm this order')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('cancel')
                ->label('Cancel order')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Removes the incoming quantities this order was holding.')
                ->visible(fn (PurchaseOrder $record) => $record->status->value !== 'cancelled')
                ->action(function (PurchaseOrder $record) {
                    app(ConfirmPurchaseOrder::class)->cancel($record);

                    Notification::make()->title('Order cancelled')->success()->send();
                    $this->refreshFormData(['status']);
                }),
        ];
    }
}
