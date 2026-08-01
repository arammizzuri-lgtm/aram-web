<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PurchaseOrderStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Confirmed = 'confirmed';
    case InProduction = 'in_production';
    case Shipped = 'shipped';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent to supplier',
            self::Confirmed => 'Confirmed',
            self::InProduction => 'In production',
            self::Shipped => 'Shipped',
            self::PartiallyReceived => 'Partially received',
            self::Received => 'Received',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent, self::Confirmed => 'info',
            self::InProduction, self::Shipped, self::PartiallyReceived => 'warning',
            self::Received => 'success',
            self::Cancelled => 'danger',
        };
    }

    /** Whether the order can still receive goods. */
    public function isOpen(): bool
    {
        return in_array($this, [
            self::Sent, self::Confirmed, self::InProduction, self::Shipped, self::PartiallyReceived,
        ], true);
    }

    /** Once confirmed, the supplier has committed and quantities stop being freely editable. */
    public function isCommitted(): bool
    {
        return ! in_array($this, [self::Draft, self::Cancelled], true);
    }
}
