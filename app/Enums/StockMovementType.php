<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StockMovementType: string implements HasColor, HasLabel
{
    case Opening = 'opening';
    case PurchaseReceipt = 'purchase_receipt';
    case PurchaseReturn = 'purchase_return';
    case SalesDelivery = 'sales_delivery';
    case SalesReturn = 'sales_return';
    case Adjustment = 'adjustment';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Damage = 'damage';
    case Revaluation = 'revaluation';

    public function getLabel(): string
    {
        return match ($this) {
            self::Opening => 'Opening balance',
            self::PurchaseReceipt => 'Purchase receipt',
            self::PurchaseReturn => 'Purchase return',
            self::SalesDelivery => 'Sales delivery',
            self::SalesReturn => 'Sales return',
            self::Adjustment => 'Adjustment',
            self::TransferIn => 'Transfer in',
            self::TransferOut => 'Transfer out',
            self::Damage => 'Damage / write-off',
            self::Revaluation => 'Cost revaluation',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Opening, self::Revaluation => 'gray',
            self::PurchaseReceipt, self::SalesReturn, self::TransferIn => 'success',
            self::SalesDelivery, self::PurchaseReturn, self::TransferOut, self::Damage => 'danger',
            self::Adjustment => 'warning',
        };
    }

    /**
     * A revaluation restates the value of stock already on hand without moving
     * any of it, so it carries a zero quantity and only touches cost.
     */
    public function isValueOnly(): bool
    {
        return $this === self::Revaluation;
    }

    /**
     * Whether this type adds cost-bearing stock, which drives the weighted
     * average cost recalculation. Adjustments are excluded because they are
     * quantity corrections, not purchases.
     */
    public function affectsAverageCost(): bool
    {
        return in_array($this, [self::Opening, self::PurchaseReceipt, self::TransferIn], true);
    }
}
