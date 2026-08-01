<?php

namespace App\Filament\Resources\Shipments\Pages;

use App\Actions\Inventory\ReceiveShipment;
use App\Filament\Resources\Shipments\ShipmentResource;
use App\Models\LandedCostRun;
use App\Models\Shipment;
use App\Services\Costing\LandedCostCalculator;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;

/**
 * The landed cost workbench: what every item in this container actually cost.
 *
 * Shows the goods value, each shipment cost with the basis it was spread by, and
 * the resulting per-unit cost broken down by charge — so any figure on screen can
 * be traced back to the invoice it came from.
 */
class ViewShipmentCosting extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ShipmentResource::class;

    protected string $view = 'filament.resources.shipments.costing';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string|Htmlable
    {
        return "Landed cost · {$this->shipment()->number}";
    }

    public function getSubheading(): string|Htmlable|null
    {
        $shipment = $this->shipment();

        return $shipment->container_number
            ? "Container {$shipment->container_number} · {$shipment->port_of_loading} → {$shipment->port_of_discharge}"
            : null;
    }

    public function shipment(): Shipment
    {
        return $this->getRecord()->loadMissing(['items.product', 'costs.type']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recalculate')
                ->label('Recalculate')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->action(fn () => $this->runCalculation(final: false)),

            Action::make('finalise')
                ->label('Finalise & apply')
                ->icon('heroicon-m-lock-closed')
                ->requiresConfirmation()
                ->modalDescription(
                    'Finalising locks this shipment for costing. Any difference against '
                    .'stock already sold is posted as a COGS correction; the original '
                    .'invoices are never edited.'
                )
                ->visible(fn () => $this->getRecord()->landed_cost_status->isProvisional())
                ->action(fn () => $this->runCalculation(final: true)),

            Action::make('receive')
                ->label('Receive into stock')
                ->icon('heroicon-m-inbox-arrow-down')
                ->color('primary')
                ->requiresConfirmation()
                ->modalDescription(
                    'Books the full outstanding quantity into the warehouse, valued at '
                    .'the landed cost shown below rather than the supplier price.'
                )
                ->visible(fn () => $this->getRecord()->status->canReceiveGoods())
                ->action(function () {
                    try {
                        app(ReceiveShipment::class)->handle($this->getRecord());
                        $this->record = $this->getRecord()->fresh();

                        Notification::make()
                            ->title('Goods received into stock')
                            ->body('Stock was valued at landed cost, so margins are correct from the first sale.')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Could not receive goods')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    private function runCalculation(bool $final): void
    {
        try {
            $shipment = $this->getRecord();
            $run = app(LandedCostCalculator::class)->calculate($shipment, $final, auth()->id());
            $run->update(['status' => 'applied', 'applied_at' => now()]);

            $shipment->update(['landed_cost_status' => $final ? 'final' : 'actual']);
            $this->record = $shipment->fresh();

            Notification::make()
                ->title($final ? 'Landed cost finalised' : 'Landed cost recalculated')
                ->body("Run v{$run->version} applied across {$run->lines()->count()} lines.")
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Could not calculate landed cost')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getCurrentRun(): ?LandedCostRun
    {
        return $this->getRecord()->landedCostRuns()
            ->with(['lines.product', 'lines.allocations.shipmentCost.type'])
            ->orderByDesc('version')
            ->first();
    }
}
