<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A drawing sent to a client in their room — a PDF or an image, kept on the
 * private disk — and what they decided: approved, or changes asked for (with
 * what to change). The decision is the record: when, and from which address.
 */
class RoomDrawing extends Model
{
    /** storage/app/private: reached only through the room, or the admin. */
    public const DISK = 'local';

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const CHANGES = 'changes';

    protected $fillable = [
        'client_room_id', 'title', 'note', 'file_path', 'file_name',
        'status', 'decision_note', 'decided_at', 'decided_ip', 'seen_at',
    ];

    protected $attributes = [
        'status' => self::PENDING,
    ];

    protected $casts = [
        'decided_at' => 'datetime',
        'seen_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // a file replaced in the admin, or the drawing deleted: its file goes
        static::updated(function (RoomDrawing $d) {
            $old = $d->getOriginal('file_path');
            if ($d->wasChanged('file_path') && $old) {
                Storage::disk(self::DISK)->delete($old);
            }
        });
        static::deleted(fn (RoomDrawing $d) => $d->file_path && Storage::disk(self::DISK)->delete($d->file_path));
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ClientRoom::class, 'client_room_id');
    }

    public function fileExists(): bool
    {
        return filled($this->file_path) && Storage::disk(self::DISK)->exists($this->file_path);
    }

    public function isImage(): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|webp)$/i', (string) $this->file_path);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /** The name it downloads as: the client's own file name, or its title. */
    public function downloadName(): string
    {
        $ext = pathinfo((string) $this->file_path, PATHINFO_EXTENSION);

        return $this->file_name ?: trim(preg_replace('/[^\pL\pN\s-]+/u', '', $this->title)).($ext ? '.'.$ext : '');
    }

    /** @return array<string, string> status => label, for the admin */
    public static function statuses(): array
    {
        return [self::PENDING => 'Waiting for the client', self::APPROVED => 'Approved', self::CHANGES => 'Changes asked'];
    }
}
