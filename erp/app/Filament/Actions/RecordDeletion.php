<?php

namespace App\Filament\Actions;

use App\Services\Deletion\DeletionImpact;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Deleting, undoing, and — rarely — erasing, the same way on every screen.
 *
 * Written once because thirteen screens need it and a user should never have to
 * remember which ones forgive. Before this, three screens could delete anything
 * at all: a tracking number typed wrong, a supplier created twice and a payment
 * that never arrived were all permanent, and the only way to blunt a wrong
 * payment was to edit the amount — which rewrites history rather than
 * correcting it.
 *
 * The rule everywhere is the same: **deleting is reversible**. What makes a
 * delete button frightening is that it is final, and none of these are. So the
 * dialog can afford to inform rather than obstruct — it says what hangs off
 * this record and what moves if it goes, names the gentler alternative when
 * there is one, and then lets you decide.
 *
 * Erasing for good is the exception, and it is barred wherever anything still
 * points at the record — which is also where the foreign keys would refuse.
 */
class RecordDeletion
{
    /**
     * Delete, restore, erase — in that order, for a table row.
     *
     * @return array<int, Action>
     */
    public static function actions(): array
    {
        return [
            self::delete(),
            self::restore(),
            self::erase(),
        ];
    }

    /**
     * Deleting, with the consequences spelled out and a way back.
     *
     * The Undo sits in the notification because that is where your attention
     * already is — a second of regret should not cost a trip to another screen.
     */
    public static function delete(): DeleteAction
    {
        return DeleteAction::make()
            ->modalHeading(fn (Model $record) => 'Delete '.self::name($record).'?')
            ->modalDescription(fn (Model $record) => self::description($record))
            ->modalSubmitActionLabel('Delete it')
            ->successNotification(fn (Model $record) => Notification::make()
                ->title(self::name($record).' deleted')
                ->success()
                ->actions([self::undo($record)]));
    }

    /**
     * Putting it back, which can fail for one reason worth explaining.
     *
     * A deleted record gives up its code — the unique checks on the forms skip
     * deleted rows on purpose, so the supplier you deleted this morning does
     * not hold "SUP-A" hostage from behind a filter nobody has opened. The
     * price of that is this: if something has since taken the code, the row
     * cannot come back as it stands. Said plainly, because "SQLSTATE[23000]"
     * is not something anybody can act on.
     */
    public static function restore(): RestoreAction
    {
        return RestoreAction::make()
            ->action(function (Model $record): void {
                try {
                    $record->restore();
                } catch (Throwable) {
                    Notification::make()
                        ->title('That one cannot come back as it is')
                        ->body('Something else has taken its code or number since. Change the newer one, then try again.')
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()->title(self::name($record).' is back')->success()->send();
            });
    }

    /**
     * Gone for good.
     *
     * Owner only, and offered only where nothing points at the record — which
     * is both the honest rule and the one the database enforces anyway. A
     * button that exists and then fails is worse than one that is absent.
     */
    public static function erase(): ForceDeleteAction
    {
        return ForceDeleteAction::make()
            ->label('Delete permanently')
            ->modalDescription('This one cannot be undone.')
            ->visible(fn (Model $record) => auth()->user()?->can('delete_record')
                && app(DeletionImpact::class)->canBeErased($record))
            /*
             * Whether anything still points at a record is worked out from a
             * list of relationships kept by hand; the database is the only
             * thing that actually knows. The guess decides whether the button
             * appears, the constraint decides whether it works.
             */
            ->action(function (Model $record): void {
                try {
                    $record->forceDelete();
                } catch (Throwable) {
                    Notification::make()
                        ->title('That one cannot be erased')
                        ->body('Something in the system still refers to it, including records '
                            .'that have themselves been deleted. It stays deleted but intact.')
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()->title('Gone for good')->success()->send();
            });
    }

    /** Deleted records are hidden by default, exactly as they always were. */
    public static function filter(): TrashedFilter
    {
        return TrashedFilter::make()->label('Deleted records');
    }

    /**
     * The Undo button.
     *
     * A dispatch rather than a closure, and not by preference: a notification
     * is serialised to the session and rebuilt by `Notification::fromArray()`,
     * so a closure would not survive the trip. The event carries the class and
     * the id, and UndoDelete — mounted on every page of the panel — puts the
     * record back.
     */
    private static function undo(Model $record): Action
    {
        return Action::make('undo')
            ->label('Undo')
            ->button()
            ->dispatch('undo-delete', [[
                'model' => $record::class,
                'key' => $record->getKey(),
            ]]);
    }

    private static function description(Model $record): string
    {
        $impact = app(DeletionImpact::class);

        $alternative = $impact->alternative($record);

        return trim($impact->describe($record).($alternative ? ' '.$alternative : ''));
    }

    /**
     * What to call this record in a sentence.
     *
     * Its number if it has one, its name if not, and the model's own label as a
     * last resort — so the dialog says "Delete D-2026-0007?" rather than
     * "Delete record?"
     */
    private static function name(Model $record): string
    {
        return self::nameOf($record)
            ?? str(class_basename($record))->headline()->lower()->toString();
    }

    /**
     * The first identifying attribute the record actually has.
     *
     * `getAttributes()` rather than `getAttribute()`: the application runs with
     * `preventAccessingMissingAttributes` outside production, so asking a
     * customer for its `number` throws rather than returning null.
     */
    public static function nameOf(Model $record): ?string
    {
        $attributes = $record->getAttributes();

        foreach (['number', 'tracking_number', 'name', 'code', 'sku'] as $attribute) {
            if (filled($attributes[$attribute] ?? null)) {
                return (string) $attributes[$attribute];
            }
        }

        return null;
    }
}
