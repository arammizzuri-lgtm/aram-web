<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceListImport extends Model
{
    protected $fillable = [
        'supplier_id', 'import_profile_id', 'original_filename', 'stored_path', 'disk',
        'status', 'sheet_name', 'header_row', 'column_map', 'currency', 'effective_date',
        'rows_total', 'rows_new', 'rows_updated', 'rows_unchanged', 'rows_error',
        'avg_change_percent', 'error_log', 'imported_by', 'committed_at', 'reverted_at',
    ];

    protected function casts(): array
    {
        return [
            'column_map' => 'array',
            'error_log' => 'array',
            'effective_date' => 'date',
            'committed_at' => 'datetime',
            'reverted_at' => 'datetime',
            'avg_change_percent' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ImportProfile::class, 'import_profile_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(PriceListImportRow::class);
    }

    public function absolutePath(): string
    {
        return storage_path('app/'.$this->stored_path);
    }

    /** Rows the operator has ticked and which would actually change something. */
    public function approvedChanges(): int
    {
        return $this->rows()
            ->where('is_approved', true)
            ->whereIn('action', ['create', 'update_price'])
            ->count();
    }

    public function suspiciousRows(): int
    {
        return $this->rows()->whereNotNull('errors')->where('action', 'update_price')->count();
    }

    public function canBeReverted(): bool
    {
        return $this->status === 'committed';
    }
}
