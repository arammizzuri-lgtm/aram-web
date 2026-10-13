<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line in the conversation under a progress update, in one client room:
 * from the client, or the studio's answer.
 */
class RoomComment extends Model
{
    protected $fillable = ['client_room_id', 'project_update_id', 'from_studio', 'body', 'seen_at'];

    protected $casts = [
        'from_studio' => 'boolean',
        'seen_at' => 'datetime',
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
}
