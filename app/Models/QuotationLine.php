<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A frozen copy of what was offered, including which photo was shown.
 *
 * The photo is stored by path rather than by media id on purpose: replacing a
 * product's picture later must not change what this quotation displayed. The
 * whole value of the approval record is that it cannot move.
 */
class QuotationLine extends Model
{
    protected $fillable = [
        'quotation_id', 'deal_line_id', 'description', 'description_ku',
        'specification', 'quantity', 'unit', 'unit_price', 'line_total',
        'photo_path', 'display_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function dealLine(): BelongsTo
    {
        return $this->belongsTo(DealLine::class, 'deal_line_id');
    }
}
