<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Currency extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'code', 'name', 'symbol', 'decimal_places', 'symbol_position',
        'is_base', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'is_base' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('currencies.base'));
        static::deleted(fn () => Cache::forget('currencies.base'));
    }

    public function ratesFrom(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'from_currency', 'code');
    }

    /** The currency all costing and reporting arithmetic happens in. */
    public static function base(): string
    {
        return Cache::rememberForever(
            'currencies.base',
            fn () => static::query()->where('is_base', true)->value('code') ?? 'USD',
        );
    }

    public function format(Money $money): string
    {
        return $money->format($this->decimal_places, $this->symbol, $this->symbol_position);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
