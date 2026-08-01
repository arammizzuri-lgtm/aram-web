<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SalesOrderStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case PartiallyDelivered = 'partially_delivered';
    case Delivered = 'delivered';
    case Invoiced = 'invoiced';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Confirmed => 'Confirmed',
            self::PartiallyDelivered => 'Partially delivered',
            self::Delivered => 'Delivered',
            self::Invoiced => 'Invoiced',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Confirmed => 'info',
            self::PartiallyDelivered => 'warning',
            self::Delivered => 'success',
            self::Invoiced => 'success',
            self::Cancelled => 'danger',
        };
    }

    /** Whether the order can still be delivered against. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Confirmed, self::PartiallyDelivered], true);
    }
}
