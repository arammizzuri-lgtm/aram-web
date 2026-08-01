<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ShipmentStatus: string implements HasColor, HasIcon, HasLabel
{
    case Planning = 'planning';
    case Booked = 'booked';
    case InTransit = 'in_transit';
    case Arrived = 'arrived';
    case Customs = 'customs';
    case Cleared = 'cleared';
    case Delivered = 'delivered';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Planning => 'Planning',
            self::Booked => 'Booked',
            self::InTransit => 'In transit',
            self::Arrived => 'Arrived at port',
            self::Customs => 'In customs',
            self::Cleared => 'Cleared',
            self::Delivered => 'Delivered',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Planning => 'gray',
            self::Booked => 'info',
            self::InTransit, self::Arrived, self::Customs => 'warning',
            self::Cleared, self::Delivered => 'success',
            self::Closed => 'gray',
            self::Cancelled => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Planning => 'heroicon-m-pencil-square',
            self::Booked => 'heroicon-m-calendar-days',
            self::InTransit => 'heroicon-m-truck',
            self::Arrived => 'heroicon-m-map-pin',
            self::Customs => 'heroicon-m-shield-check',
            self::Cleared => 'heroicon-m-check-badge',
            self::Delivered => 'heroicon-m-home-modern',
            self::Closed => 'heroicon-m-lock-closed',
            self::Cancelled => 'heroicon-m-x-circle',
        };
    }

    /** Goods paid for but not yet received — an asset, not an expense. */
    public function isInTransit(): bool
    {
        return in_array($this, [self::Booked, self::InTransit, self::Arrived, self::Customs], true);
    }

    public function canReceiveGoods(): bool
    {
        return in_array($this, [self::Cleared, self::Delivered], true);
    }
}
