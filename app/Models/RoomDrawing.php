<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * A drawing sent to a client in their room — a PDF or an image, kept on the
 * private disk — and what they decided: approved, or changes asked for (with
 * what to change). The decision is the record: when, and from which address.
 * Their comments can also be pinned to spots on it (RoomComment).
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

    /** The comments pinned to it, oldest first. */
    public function comments(): HasMany
    {
        return $this->hasMany(RoomComment::class)->oldest()->orderBy('id');
    }

    /**
     * Its pins: a thread to each spot, numbered 1, 2, 3… in the order they
     * were first made — the same numbers in the room and in the admin.
     *
     * @return Collection<int, Collection<int, RoomComment>>
     */
    public function pins(): Collection
    {
        return $this->comments->groupBy(fn (RoomComment $c) => $c->spot())->values()
            ->mapWithKeys(fn (Collection $thread, int $i) => [$i + 1 => $thread->values()]);
    }

    /** The pins as the room's drawing viewer reads them (room-pins.js). */
    public function pinData(string $lang): array
    {
        return $this->pins()->map(fn (Collection $t, int $n) => [
            'n' => $n, 'page' => $t[0]->page, 'x' => (float) $t[0]->x, 'y' => (float) $t[0]->y,
            'msgs' => $t->map(fn (RoomComment $c) => [
                'studio' => $c->from_studio,
                'who' => ($c->from_studio ? bival('room_studio') : bival('room_you')).' · '.site_date($c->created_at, $lang),
                'body' => $c->body,
            ])->all(),
        ])->values()->all();
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
