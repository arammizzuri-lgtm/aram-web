<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line in a conversation in one client room, from the client or the
 * studio's answer: under a progress update, or pinned to a spot on a drawing
 * (its page, and x/y as fractions of the page from the top left).
 */
class RoomComment extends Model
{
    protected $fillable = ['client_room_id', 'project_update_id', 'room_drawing_id', 'page', 'x', 'y', 'from_studio', 'body', 'seen_at'];

    protected $casts = [
        'from_studio' => 'boolean',
        'seen_at' => 'datetime',
        'page' => 'integer',
        'x' => 'decimal:5',
        'y' => 'decimal:5',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(ClientRoom::class, 'client_room_id');
    }

    /** The progress update it is under (not "update": Eloquent has one). */
    public function projectUpdate(): BelongsTo
    {
        return $this->belongsTo(ProjectUpdate::class, 'project_update_id');
    }

    /** The drawing it is pinned to. */
    public function drawing(): BelongsTo
    {
        return $this->belongsTo(RoomDrawing::class, 'room_drawing_id');
    }

    /** Where on the drawing: every comment at the same spot is one thread. */
    public function spot(): string
    {
        return $this->page.':'.$this->x.':'.$this->y;
    }

    /** Its answers go to the same place: the same update, or the same spot. */
    public function place(): array
    {
        return $this->room_drawing_id
            ? $this->only(['room_drawing_id', 'page', 'x', 'y'])
            : ['project_update_id' => $this->project_update_id];
    }
}
