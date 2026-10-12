<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Where an enquiry stands, from the moment it arrives to the moment it is
 * decided. The admin badges and filters read their labels and colours here.
 */
enum LeadStatus: string implements HasColor, HasIcon, HasLabel
{
    case New = 'new';
    case Contacted = 'contacted';
    case Quoted = 'quoted';
    case Won = 'won';
    case Lost = 'lost';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Quoted => 'Proposal sent',
            self::Won => 'Won',
            self::Lost => 'Lost',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Contacted => 'warning',
            self::Quoted => 'primary',
            self::Won => 'success',
            self::Lost => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::New => Heroicon::OutlinedSparkles,
            self::Contacted => Heroicon::OutlinedChatBubbleLeftRight,
            self::Quoted => Heroicon::OutlinedDocumentText,
            self::Won => Heroicon::OutlinedTrophy,
            self::Lost => Heroicon::OutlinedXCircle,
        };
    }

    /** Still being worked on — neither won nor lost. */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Won, self::Lost], true);
    }
}
