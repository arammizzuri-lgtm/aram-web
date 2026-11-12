<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * A file uploaded for a presentation — a render, a drawing, a PDF — or, with
 * no presentation, one of the studio's logos. The original is kept untouched
 * on the private disk, named by its SHA-256; the editor works on light
 * previews of it and the PDF export reads the original.
 */
class PresentationAsset extends Model
{
    public const DISK = 'local';

    public const DIR = 'presentations';

    /** The largest single file taken, in bytes. */
    public const MAX_BYTES = 300 * 1024 * 1024;

    protected $fillable = ['presentation_id', 'hash', 'kind', 'ext', 'name', 'size', 'meta'];

    protected $casts = ['meta' => 'array'];

    protected static function booted(): void
    {
        // the file and its previews go with the last row that names them
        static::deleted(function (PresentationAsset $asset) {
            if (static::where('hash', $asset->hash)->exists()) {
                return;
            }
            $disk = Storage::disk(self::DISK);
            $disk->delete($asset->originalPath());
            $disk->delete(array_filter(
                $disk->files(self::DIR.'/previews'),
                fn (string $file) => str_starts_with(basename($file), $asset->hash),
            ));
        });
    }

    public function originalPath(): string
    {
        return self::DIR."/originals/{$this->hash}.{$this->ext}";
    }

    /** A preview or thumbnail made in the browser; $page is 1-based for a PDF, 0 for an image. */
    public static function previewPath(string $hash, string $variant, int $page = 0): string
    {
        return self::DIR."/previews/{$hash}".($page ? "-p{$page}" : '')."-{$variant}";
    }

    /** JPEG, PNG or PDF — told from the file's first bytes, never from its name. */
    public static function sniff(string $path): ?array
    {
        $head = (string) @file_get_contents($path, false, null, 0, 1024);

        return match (true) {
            str_starts_with($head, "\xFF\xD8\xFF") => ['image', 'jpg'],
            str_starts_with($head, "\x89PNG\r\n\x1A\n") => ['image', 'png'],
            str_contains($head, '%PDF-') => ['pdf', 'pdf'],
            default => null,
        };
    }

    /**
     * Take a file in: check what it really is, store it under its hash (once,
     * however many presentations use it) and record it.
     */
    public static function import(string $path, string $name, ?int $presentationId = null, bool $move = false): self
    {
        $type = self::sniff($path);
        if (! $type) {
            throw new InvalidArgumentException('Only JPG and PNG images and PDF files can be used.');
        }
        [$kind, $ext] = $type;
        $meta = [];
        if ($kind === 'image') {
            $size = @getimagesize($path);
            if (! $size) {
                throw new InvalidArgumentException('This image cannot be read — it may be damaged.');
            }
            $meta = ['w' => $size[0], 'h' => $size[1]];
        }

        $hash = hash_file('sha256', $path);
        $asset = new self(['presentation_id' => $presentationId, 'hash' => $hash, 'kind' => $kind, 'ext' => $ext,
            'name' => mb_substr($name, 0, 200), 'size' => filesize($path), 'meta' => $meta]);
        $disk = Storage::disk(self::DISK);
        if (! $disk->exists($asset->originalPath())) {
            $disk->makeDirectory(self::DIR.'/originals');
            $target = $disk->path($asset->originalPath());
            $move ? rename($path, $target) : copy($path, $target);
        }
        if ($move) {
            @unlink($path);
        }

        // the same file again in the same presentation is the same asset
        return self::where('presentation_id', $presentationId)->where('hash', $hash)->first() ?? tap($asset)->save();
    }

    /** What the editor needs to know about it. */
    public function toEditor(): array
    {
        return [
            'id' => $this->id, 'hash' => $this->hash, 'kind' => $this->kind, 'ext' => $this->ext,
            'name' => $this->name, 'size' => $this->size, 'meta' => (object) ($this->meta ?? []),
            'brand' => $this->presentation_id === null,
        ];
    }
}
