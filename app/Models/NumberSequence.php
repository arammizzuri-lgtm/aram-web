<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NumberSequence extends Model
{
    protected $fillable = [
        'document_type', 'year', 'prefix', 'format', 'padding', 'next_number',
    ];

    /**
     * Mirrored from the column defaults so a freshly created row can render a
     * number straight away. Relying on the database defaults alone leaves these
     * null on the in-memory model until it is reloaded.
     */
    protected $attributes = [
        'format' => '{prefix}-{year}-{number}',
        'padding' => 4,
        'next_number' => 1,
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'padding' => 'integer',
            'next_number' => 'integer',
        ];
    }

    /** Render a number for this sequence without consuming it. */
    public function render(int $number): string
    {
        return strtr($this->format, [
            '{prefix}' => $this->prefix,
            '{year}' => (string) $this->year,
            '{number}' => str_pad((string) $number, $this->padding, '0', STR_PAD_LEFT),
        ]);
    }
}
