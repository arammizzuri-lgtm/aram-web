<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ExchangeRate extends Model
{
    protected $fillable = [
        'from_currency', 'to_currency', 'rate', 'effective_date', 'source', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:8',
        ];
    }

    /**
     * Kept as a bare `Y-m-d` in the database.
     *
     * Laravel's default date cast writes `Y-m-d H:i:s` even into a DATE column,
     * which then fails to match an equality lookup on a plain date — and a rate
     * has no meaningful time of day anyway.
     */
    protected function effectiveDate(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : Carbon::parse($value)->startOfDay(),
            set: fn (mixed $value) => Carbon::parse($value)->toDateString(),
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The rate in force on a date: the newest one published on or before it. */
    public function scopeInForce(Builder $query, string $from, string $to, mixed $on): Builder
    {
        return $query->where('from_currency', $from)
            ->where('to_currency', $to)
            ->whereDate('effective_date', '<=', $on)
            ->orderByDesc('effective_date')
            ->orderByDesc('id');
    }
}
