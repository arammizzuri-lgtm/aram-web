<?php

namespace App\Filament\Pages;

use App\Actions\Catalog\CommitPriceListImport;
use App\Models\ImportProfile;
use App\Models\PriceListImport;
use App\Models\Supplier;
use App\Services\Import\PriceListMatcher;
use App\Services\Import\SheetReader;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Throwable;
use UnitEnum;

/**
 * Upload → map → review → commit, with an undo at the end.
 *
 * The review step is the whole point: a supplier spreadsheet is untrusted input,
 * and the operator sees every proposed change — priced up, priced down, brand
 * new, or suspicious — before anything touches the catalogue.
 */
class PriceListImportPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Price list import';

    // Otherwise /admin/price-list-import-page — see the note in ReportsPage.
    protected static ?string $slug = 'price-list-import';

    protected static ?string $title = 'Import a supplier price list';

    protected string $view = 'filament.pages.price-list-import';

    /** @var array<string, mixed> */
    public array $data = [];

    public ?int $importId = null;

    public function mount(): void
    {
        // Every key the form uses has to be seeded here; a component default is
        // not applied to state that fill() does not mention.
        $this->form->fill([
            'currency' => 'USD',
            'header_row' => 1,
            'effective_date' => today(),
            'save_profile' => true,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(2)
            ->components([
                Select::make('supplier_id')
                    ->label('Supplier')
                    ->options(fn () => Supplier::query()->active()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->live()
                    ->helperText(fn ($state) => $this->profileFor($state)
                        ? 'A saved layout exists for this supplier — mapping will be filled in automatically.'
                        : 'The column layout will be guessed, then saved for next time.'),

                Select::make('currency')
                    ->options(['USD' => 'USD', 'CNY' => 'CNY', 'IQD' => 'IQD'])
                    ->default('USD')
                    ->required(),

                TextInput::make('header_row')
                    ->label('Header row')
                    ->numeric()
                    ->default(1)
                    ->required()
                    ->helperText('Which row holds the column titles. Often not row 1.'),

                DatePicker::make('effective_date')
                    ->label('Prices effective from')
                    ->default(today())
                    ->required(),

                FileUpload::make('file')
                    ->label('Price list')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'text/csv',
                    ])
                    ->maxSize(20480)
                    ->disk('local')
                    ->directory('price-lists')
                    ->required()
                    ->columnSpanFull()
                    ->helperText('Excel or CSV, up to 20 MB.'),

                Toggle::make('save_profile')
                    ->label('Remember this layout for the supplier')
                    ->default(true)
                    ->columnSpanFull(),
            ]);
    }

    public function analyse(): void
    {
        $data = $this->form->getState();

        try {
            $path = is_array($data['file']) ? reset($data['file']) : $data['file'];

            $import = PriceListImport::create([
                'supplier_id' => $data['supplier_id'],
                'original_filename' => basename((string) $path),
                'stored_path' => $path,
                'disk' => 'local',
                'status' => 'parsing',
                'header_row' => (int) $data['header_row'],
                'currency' => $data['currency'],
                'effective_date' => $data['effective_date'],
                'imported_by' => auth()->id(),
            ]);

            $reader = app(SheetReader::class);
            $absolute = Storage::disk('local')->path($path);
            $rows = $reader->read($absolute);

            if ($rows === []) {
                throw new \RuntimeException('That file has no readable rows.');
            }

            $headerIndex = max(0, (int) $data['header_row'] - 1);
            $profile = $this->profileFor($data['supplier_id']);

            $mapping = $profile?->column_map
                ?? $reader->guessMapping($rows[$headerIndex] ?? []);

            if (! isset($mapping['supplier_sku'], $mapping['unit_price'])) {
                throw new \RuntimeException(
                    'Could not identify a supplier SKU column and a price column. '
                    .'Check the header row number, or set up a layout for this supplier.'
                );
            }

            app(PriceListMatcher::class)->build($import, $rows, $mapping, $headerIndex + 2);

            if ($data['save_profile'] ?? false) {
                ImportProfile::updateOrCreate(
                    ['supplier_id' => $data['supplier_id'], 'name' => 'Default'],
                    [
                        'header_row' => (int) $data['header_row'],
                        'first_data_row' => $headerIndex + 2,
                        'column_map' => $mapping,
                        'currency' => $data['currency'],
                        'is_default' => true,
                    ],
                );
            }

            $this->importId = $import->id;

            Notification::make()->title('Price list analysed')->success()->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Could not read that price list')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getImport(): ?PriceListImport
    {
        return $this->importId ? PriceListImport::find($this->importId) : null;
    }

    public function toggleRow(int $rowId): void
    {
        $row = $this->getImport()?->rows()->find($rowId);

        $row?->forceFill(['is_approved' => ! $row->is_approved])->save();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('commit')
                ->label('Commit approved changes')
                ->icon('heroicon-m-check')
                ->requiresConfirmation()
                ->modalDescription(fn () => sprintf(
                    'This will apply %d changes to the catalogue. It can be undone afterwards.',
                    $this->getImport()?->approvedChanges() ?? 0,
                ))
                ->visible(fn () => $this->getImport()?->status === 'previewed')
                ->action(function () {
                    try {
                        $import = app(CommitPriceListImport::class)->handle($this->getImport());

                        Notification::make()
                            ->title('Price list committed')
                            ->body("{$import->rows_new} created, {$import->rows_updated} repriced.")
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Commit failed')->body($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('revert')
                ->label('Undo this import')
                ->icon('heroicon-m-arrow-uturn-left')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Restores every price this import changed, using the recorded history.')
                ->visible(fn () => $this->getImport()?->canBeReverted() ?? false)
                ->action(function () {
                    try {
                        app(CommitPriceListImport::class)->revert($this->getImport());

                        Notification::make()->title('Import reverted')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Could not revert')->body($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('startOver')
                ->label('Start over')
                ->color('gray')
                ->visible(fn () => $this->importId !== null)
                ->action(fn () => $this->importId = null),
        ];
    }

    private function profileFor(mixed $supplierId): ?ImportProfile
    {
        return $supplierId
            ? ImportProfile::where('supplier_id', $supplierId)->where('is_default', true)->first()
            : null;
    }
}
