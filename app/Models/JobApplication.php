<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Someone applying to work at the studio — for a listed position, or with
 * an open application — and their CV, kept on the private disk.
 */
class JobApplication extends Model
{
    /** The disk CVs live on: storage/app/private, never served publicly. */
    public const DISK = 'local';

    protected $fillable = [
        'job_opening_id', 'name', 'email', 'phone', 'portfolio_url', 'message',
        'cv_path', 'cv_name', 'cv_size', 'lang', 'source', 'status', 'notes', 'is_read', 'ip_address',
    ];

    protected $attributes = [
        'status' => 'new',
    ];

    protected $casts = [
        'status' => ApplicationStatus::class,
        'is_read' => 'boolean',
        'cv_size' => 'integer',
    ];

    protected static function booted(): void
    {
        // the CV goes with the application
        static::deleted(fn (JobApplication $a) => $a->cv_path && Storage::disk(self::DISK)->delete($a->cv_path));
    }

    public function opening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class, 'job_opening_id');
    }

    public function positionLabel(): string
    {
        return $this->opening?->title_en ?? 'Open application';
    }

    public function cvExists(): bool
    {
        return filled($this->cv_path) && Storage::disk(self::DISK)->exists($this->cv_path);
    }

    /** "1.4 MB" */
    public function cvSizeLabel(): string
    {
        return $this->cv_size >= 1048576
            ? round($this->cv_size / 1048576, 1).' MB'
            : max(1, round($this->cv_size / 1024)).' KB';
    }

    public function whatsappUrl(): ?string
    {
        $digits = phone_digits($this->phone);

        return $digits ? 'https://wa.me/'.$digits : null;
    }
}
