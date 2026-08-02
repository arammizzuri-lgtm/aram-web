<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * A resource whose deleted records can still be reached.
 *
 * Soft deleting hides a row from every query by default, which on its own is
 * the worst of both worlds: the rows pile up invisibly and the delete cannot be
 * taken back. Dropping the scope here is what lets the "Deleted records" filter
 * find them again. The filter still hides them unless asked, so the screen
 * reads exactly as it did before.
 */
trait KeepsDeletedRecords
{
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
