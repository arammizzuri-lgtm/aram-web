<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrystalPriceHistory extends Model
{
    protected $table = 'crystal_price_history';

    protected $fillable = [
        'crystal_price_id', 'price', 'previous_price', 'change_percent',
        'currency', 'effective_date', 'source', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'previous_price' => 'decimal:4',
            'change_percent' => 'decimal:2',
            'effective_date' => 'date',
        ];
    }

    public function crystalPrice(): BelongsTo
    {
        return $this->belongsTo(CrystalPrice::class);
    }
}
