<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceTier extends Model
{
    protected $fillable = ['name', 'code', 'default_discount_percent', 'is_default', 'sort_order'];

    protected function casts(): array
    {
        return [
            'default_discount_percent' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
