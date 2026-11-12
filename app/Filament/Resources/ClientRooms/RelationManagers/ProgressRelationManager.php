<?php

namespace App\Filament\Resources\ClientRooms\RelationManagers;

use App\Filament\Resources\Projects\RelationManagers\UpdatesRelationManager;
use Filament\Tables\Table;

/**
 * The room's progress: the same updates as its project's, added from here and
 * private unless switched off — what the client sees, and comments under.
 */
class ProgressRelationManager extends UpdatesRelationManager
{
    protected static string $relationship = 'progress';

    protected static ?string $title = 'Progress';

    protected bool $privateByDefault = true;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->emptyStateDescription('Add a dated note and site photos after each visit. They stay private — only in this client\'s room — and the client can comment under each one. Then use WhatsApp → "Tell them there is something new".');
    }
}
