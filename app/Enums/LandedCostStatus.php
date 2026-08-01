<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Containers routinely arrive and start selling weeks before the clearance
 * agent's real invoice turns up, so costing is provisional first and reconciled
 * afterwards. Anything not yet Final is shown with a badge wherever the cost
 * appears, so nobody quotes a price off a guess without knowing it is one.
 */
enum LandedCostStatus: string implements HasColor, HasLabel
{
    case None = 'none';
    case Estimated = 'estimated';
    case Actual = 'actual';
    case Final = 'final';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'Not costed',
            self::Estimated => 'Estimated',
            self::Actual => 'Actuals partly in',
            self::Final => 'Final',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::None => 'gray',
            self::Estimated => 'warning',
            self::Actual => 'info',
            self::Final => 'success',
        };
    }

    public function isProvisional(): bool
    {
        return $this !== self::Final;
    }
}
