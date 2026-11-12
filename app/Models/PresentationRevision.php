<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A presentation's document as it stood when a revision was issued. */
class PresentationRevision extends Model
{
    protected $fillable = ['presentation_id', 'key', 'label', 'note', 'doc'];

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(Presentation::class);
    }

    /** What the editor lists. */
    public function toEditor(): array
    {
        return ['id' => $this->id, 'key' => $this->key, 'label' => $this->label, 'note' => $this->note, 'at' => $this->created_at->toIso8601String()];
    }
}
