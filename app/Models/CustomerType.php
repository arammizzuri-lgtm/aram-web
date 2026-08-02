<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Wholesale, regular, whatever else you decide.
 *
 * Named by you rather than fixed in code: adding a type is a row, not a change
 * to the system. Decides which selling price a product uses for a customer.
 */
class CustomerType extends Model
{
    protected $fillable = [
        'name', 'code', 'description', 'default_markup_percent',
        'is_default', 'display_order',
    ];

    protected function casts(): array
    {
        return [
            'default_markup_percent' => 'decimal:4',
            'is_default' => 'boolean',
        ];
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->first();
    }
}
