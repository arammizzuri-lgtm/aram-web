<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * The notes of a meeting with a client, in their room: when, who came, what
 * was decided (one decision per line, shown numbered) and a photo or PDF if
 * there is one (private disk). The client agrees, or says something is not
 * right; the studio corrects the notes and they are asked again.
 *
 * seen_at: null while the studio has not read the client's answer.
 */
class RoomMeeting extends Model
{
    public const DISK = 'local';

    public const PENDING = 'pending';

    public const AGREED = 'agreed';

    public const OBJECTED = 'objected';

    protected $fillable = [
        'client_room_id', 'met_on', 'title', 'attendees', 'decisions', 'file_path', 'file_name',
        'status', 'client_note', 'decided_at', 'decided_ip', 'seen_at',
    ];

    protected $attributes = [
        'status' => self::PENDING,
    ];

    protected $casts = [
        'met_on' => 'date',
        'decided_at' => 'datetime',
        'seen_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updated(function (RoomMeeting $m) {
            $old = $m->getOriginal('file_path');
            if ($m->wasChanged('file_path') && $old) {
                Storage::disk(self::DISK)->delete($old);
            }
        });
        static::deleted(fn (RoomMeeting $m) => $m->file_path && Storage::disk(self::DISK)->delete($m->file_path));
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ClientRoom::class, 'client_room_id');
    }

    /** @return list<string> the decisions, one per non-empty line */
    public function decisionList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $this->decisions)), 'strlen'));
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function fileExists(): bool
    {
        return filled($this->file_path) && Storage::disk(self::DISK)->exists($this->file_path);
    }

    public function isImage(): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|webp)$/i', (string) $this->file_path);
    }

    /** @return array<string, string> status => label, for the admin */
    public static function statuses(): array
    {
        return [self::PENDING => 'Waiting for the client', self::AGREED => 'Agreed', self::OBJECTED => 'Something is not right'];
    }
}
