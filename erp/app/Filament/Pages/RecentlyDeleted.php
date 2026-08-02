<?php

namespace App\Filament\Pages;

use App\Filament\Actions\RecordDeletion;
use App\Livewire\UndoDelete;
use App\Models\DealPurchase;
use App\Models\SupplierPayment;
use App\Services\Deletion\DeletionImpact;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;
use UnitEnum;

/**
 * Everything deleted, in one place, with a way back.
 *
 * Each screen has a "Deleted records" filter of its own, which is the right
 * thing when you know where you left something. This is for when you do not:
 * one list, every kind of record, newest first — which is what makes deleting
 * feel safe enough to actually use. A delete you cannot look at again is one
 * nobody dares make.
 *
 * The rows are not one model, so the table is built from arrays rather than a
 * query. Each row carries the class and key it came from and nothing else needs
 * to know what it is.
 */
class RecentlyDeleted extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Recently deleted';

    protected static ?string $title = 'Recently deleted';

    protected string $view = 'filament.pages.recently-deleted';

    /**
     * How far back to look.
     *
     * Not a purge — nothing is ever thrown away on a timer here, because a
     * system that quietly destroys your records after a month is exactly the
     * thing this page exists to stop being afraid of. It is only how much of
     * the list is worth showing at once.
     */
    private const DAYS = 90;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Deleted in the last '.self::DAYS.' days')
            ->description('Restoring puts a record back exactly where it was.')
            ->emptyStateHeading('Nothing has been deleted')
            ->emptyStateDescription('Anything you delete anywhere in the system appears here.')
            ->records(fn (): Collection => $this->deletedRecords())
            ->columns([
                TextColumn::make('type')
                    ->label('What')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('name')
                    ->label('Which one')
                    ->weight('medium')
                    ->searchable(),

                TextColumn::make('deleted_at')
                    ->label('Deleted')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->action(fn (array $record) => $this->restore($record)),

                Action::make('erase')
                    ->label('Delete permanently')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('This one cannot be undone.')
                    ->visible(fn (array $record) => auth()->user()?->can('delete_record')
                        && $this->canBeErased($record))
                    ->action(fn (array $record) => $this->erase($record)),
            ]);
    }

    /**
     * Every deleted record across every model that keeps them.
     *
     * A query per model rather than anything clever: there are fifteen of them,
     * each one is indexed on `deleted_at`, and the alternative — a union across
     * tables with nothing in common but a timestamp — would be harder to read
     * for no gain anybody could measure.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function deletedRecords(): Collection
    {
        $since = now()->subDays(self::DAYS);

        return collect(UndoDelete::restorable())
            ->flatMap(function (string $class) use ($since): array {
                if (! $this->canSee($class)) {
                    return [];
                }

                return $class::onlyTrashed()
                    ->where('deleted_at', '>=', $since)
                    ->latest('deleted_at')
                    ->get()
                    ->map(fn (Model $record): array => [
                        '__key' => $class.':'.$record->getKey(),
                        'type' => str(class_basename($class))->headline()->toString(),
                        'name' => $this->name($record),
                        'deleted_at' => $record->deleted_at,
                    ])
                    ->all();
            })
            ->sortByDesc('deleted_at')
            ->values();
    }

    /**
     * The cost boundary, kept here too.
     *
     * A purchase is nothing but cost, so an assistant seeing a deleted one in a
     * list — supplier, number and all — would be a leak through the back of a
     * screen they cannot open from the front.
     *
     * @param  class-string<Model>  $class
     */
    private function canSee(string $class): bool
    {
        return match ($class) {
            DealPurchase::class,
            SupplierPayment::class => auth()->user()?->can('view_cost') ?? false,
            default => true,
        };
    }

    private function restore(array $record): void
    {
        $model = $this->resolve($record);

        if ($model === null) {
            return;
        }

        try {
            $model->restore();
        } catch (Throwable $e) {
            /*
             * Almost always a unique code that has since been given to
             * something else — the supplier you deleted and then recreated
             * under the same code. Said plainly, because "SQLSTATE[23000]" is
             * not an answer anybody can act on.
             */
            Notification::make()
                ->title('That one cannot come back as it is')
                ->body('Something else has taken its code or number since. Change the newer one, then try again.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()->title($this->name($model).' is back')->success()->send();
    }

    /**
     * Erasing, with the database given the last word.
     *
     * Whether anything still points at a record is worked out from a list of
     * relationships kept by hand, and the database is the only thing that
     * actually knows. So the answer from the guess decides whether the button
     * appears, and the constraint decides whether it works — rather than a
     * stack trace deciding for both.
     */
    private function erase(array $record): void
    {
        $model = $this->resolve($record);

        if ($model === null) {
            return;
        }

        try {
            $model->forceDelete();
        } catch (Throwable) {
            Notification::make()
                ->title('That one cannot be erased')
                ->body('Something in the system still refers to it, including records that '
                    .'have themselves been deleted. It stays here, deleted but intact.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()->title('Gone for good')->success()->send();
    }

    private function canBeErased(array $record): bool
    {
        $model = $this->resolve($record);

        return $model !== null && app(DeletionImpact::class)->canBeErased($model);
    }

    /**
     * The row's class and key, back into the record it names.
     *
     * Checked against the same allowlist the Undo button uses — a table row is
     * as much a thing the browser sends back as an event is.
     */
    private function resolve(array $record): ?Model
    {
        [$class, $key] = array_pad(explode(':', (string) ($record['__key'] ?? '')), 2, null);

        if (! in_array($class, UndoDelete::restorable(), true) || blank($key)) {
            return null;
        }

        return $class::withTrashed()->find($key);
    }

    private function name(Model $record): string
    {
        return RecordDeletion::nameOf($record) ?? '#'.$record->getKey();
    }
}
