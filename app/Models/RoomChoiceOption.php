<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One option of a finish choice: its name, a picture (private disk), what it
 * adds to the price (0: included) and, if set, its supplier from Partners.
 */
class RoomChoiceOption extends Model
{
    public const DISK = 'local';

    protected $fillable = ['room_choice_id', 'name', 'details', 'image_path', 'extra_cost', 'partner_id', 'sort_order'];

    protected $casts = [
        'extra_cost' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updated(function (RoomChoiceOption $o) {
            $old = $o->getOriginal('image_path');
            if ($o->wasChanged('image_path') && $old) {
                Storage::disk(self::DISK)->delete($old);
            }
        });
        static::deleted(fn (RoomChoiceOption $o) => $o->image_path && Storage::disk(self::DISK)->delete($o->image_path));
    }

    public function choice(): BelongsTo
    {
        return $this->belongsTo(RoomChoice::class, 'room_choice_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function imageExists(): bool
    {
        return filled($this->image_path) && Storage::disk(self::DISK)->exists($this->image_path);
    }
}
