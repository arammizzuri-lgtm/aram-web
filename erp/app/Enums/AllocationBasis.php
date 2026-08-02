<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * How a shipment-level cost is spread across the items in the container.
 *
 * Choosing this correctly per cost is the single thing that separates a truthful
 * landed cost from a plausible-looking lie. A container of crystal chandeliers and
 * flat-pack sofas splits sea freight by the space consumed, insurance by declared
 * value, and customs duty per HS code — three completely different distributions.
 * Spreading everything by value, the usual shortcut, under-costs bulky goods badly.
 *
 * @see docs/04-LANDED-COST.md
 */
enum AllocationBasis: string implements HasDescription, HasLabel
{
    case Value = 'value';
    case Weight = 'weight';
    case Volume = 'volume';
    case Quantity = 'quantity';
    case PerLineHs = 'per_line_hs';
    case Manual = 'manual';
    case None = 'none';

    public function getLabel(): string
    {
        return match ($this) {
            self::Value => 'By value',
            self::Weight => 'By weight',
            self::Volume => 'By volume (CBM)',
            self::Quantity => 'By quantity',
            self::PerLineHs => 'Per HS code (duty)',
            self::Manual => 'Manual split',
            self::None => 'Excluded from cost',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Value => 'Split in proportion to goods value. Correct for insurance, clearance fees and bank charges.',
            self::Weight => 'Split by kilograms. Correct for air freight and courier.',
            self::Volume => 'Split by CBM. Correct for sea freight, port charges and inland trucking — bulky goods consume the container.',
            self::Quantity => 'Split evenly per unit. Correct for per-item handling or labelling.',
            self::PerLineHs => 'Not a split at all: each line is charged its own HS duty rate on its CIF value.',
            self::Manual => 'You enter the amount per line. Use when a charge applies to one product only.',
            self::None => 'Recorded against the shipment but kept out of product cost.',
        };
    }

    /**
     * The pass a cost is allocated in is a property of the cost *type*, not of
     * the basis: insurance is value-based like a clearance fee, yet belongs in
     * pass 1 because it forms part of the CIF value duty is charged on. See
     * `shipment_cost_types.calculation_pass`.
     */
    public function isDuty(): bool
    {
        return $this === self::PerLineHs;
    }
}
