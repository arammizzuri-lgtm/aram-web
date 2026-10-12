<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Where a job application stands. */
enum ApplicationStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Reviewing = 'reviewing';
    case Interview = 'interview';
    case Offered = 'offered';
    case Hired = 'hired';
    case Declined = 'declined';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Reviewing => 'Reviewing',
            self::Interview => 'Interview',
            self::Offered => 'Offer made',
            self::Hired => 'Hired',
            self::Declined => 'Declined',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Reviewing => 'warning',
            self::Interview => 'primary',
            self::Offered => 'primary',
            self::Hired => 'success',
            self::Declined => 'gray',
        };
    }
}
