<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'name', 'legal_name', 'tax_number', 'registration_number', 'address', 'city',
        'country', 'phone', 'email', 'website', 'logo_path', 'stamp_path',
        'base_currency', 'fiscal_year_start_month', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'fiscal_year_start_month' => 'integer',
        ];
    }

    /**
     * Memoised for the request only.
     *
     * Deliberately not a persistent cache: serialising an Eloquent model into
     * the cache store and reading it back yields __PHP_Incomplete_Class as soon
     * as the payload outlives the class definition. A single-row lookup once per
     * request costs nothing by comparison.
     */
    private static ?self $current = null;

    protected static function booted(): void
    {
        static::saved(fn () => static::$current = null);
        static::deleted(fn () => static::$current = null);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /** v1 runs a single company; this is the seam multi-company will hang off. */
    public static function current(): ?self
    {
        return static::$current ??= static::query()->first();
    }

    /** Drop the memo — needed between requests in long-running workers and tests. */
    public static function forgetCurrent(): void
    {
        static::$current = null;
    }
}
