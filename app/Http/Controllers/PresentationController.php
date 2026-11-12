<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Presentations\PresentationResource;
use App\Models\Presentation;
use App\Models\PresentationAsset;
use App\Models\PresentationPreset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The presentation editor's server side, registered inside the panel's
 * authenticated routes (AdminPanelProvider): the editor page, saving its
 * document, taking uploads in pieces, and serving the private files back.
 * The pages are laid out and the PDF is built in the browser
 * (public/js/presentations).
 */
class PresentationController extends Controller
{
    private const ROUTE = 'filament.studio-aks-panel.presentation-';

    public function editor(Presentation $presentation): View
    {
        return $this->page($presentation);
    }

    /** The same app with no presentation open: branding and saved templates. */
    public function brand(): View
    {
        return $this->page(null);
    }

    private function page(?Presentation $presentation): View
    {
        $brand = PresentationPreset::brand();   // first, so the shipped logos exist as assets
        $url = fn (string $name, array $params = []) => route(self::ROUTE.$name, $params, false);

        return view('presentations.editor', [
            'title' => ($presentation?->name ?? 'Branding & templates').' — Presentations',
            'boot' => [
                'presentation' => $presentation
                    ? ['id' => $presentation->id, 'name' => $presentation->name, 'doc' => json_decode($presentation->doc)]
                    : null,
                'assets' => PresentationAsset::query()->whereNull('presentation_id')
                    ->when($presentation, fn ($q) => $q->orWhere('presentation_id', $presentation->id))
                    ->orderBy('id')->get()->map->toEditor()->values(),
                'brand' => json_decode($brand->data),
                'presets' => PresentationPreset::whereIn('type', ['theme', 'layout'])->orderBy('name')->get()
                    ->map(fn (PresentationPreset $p) => self::preset($p))->values(),
                'urls' => [
                    'list' => PresentationResource::getUrl(isAbsolute: false),
                    'save' => $presentation ? $url('save', ['presentation' => $presentation]) : null,
                    'chunk' => $url('chunk'),
                    'previews' => $url('previews', ['asset' => '__ID__']),
                    'asset' => $url('asset', ['asset' => '__ID__']),
                    'file' => $url('file', ['hash' => '__HASH__', 'variant' => '__VARIANT__']),
                    'presets' => $url('presets'),
                    'preset' => $url('preset', ['preset' => '__ID__']),
                    'brand' => $url('brand-save'),
                    'fonts' => '/fonts/presentations',
                    'vendor' => '/vendor/presentations',
                ],
                'csrf' => csrf_token(),
                'chunkBytes' => self::chunkBytes(),
                'maxBytes' => PresentationAsset::MAX_BYTES,
            ],
        ]);
    }

    public function save(Request $request, Presentation $presentation): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:160', 'doc' => 'required|string|max:4000000']);
        $doc = json_decode($data['doc'], true);
        abort_unless(is_array($doc) && is_array($doc['pages'] ?? null), 422, 'The presentation could not be read.');

        $short = fn ($value) => is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, 250) : null;
        $presentation->update([
            'name' => $data['name'],
            'doc' => $data['doc'],
            'project_name' => $short($doc['info']['project'] ?? null),
            'client_name' => $short($doc['info']['client'] ?? null),
            'page_count' => count($doc['pages']),
        ]);

        return response()->json(['saved_at' => $presentation->updated_at->toIso8601String()]);
    }

    /**
     * One piece of an upload. The pieces arrive in order and are joined on
     * the private disk; with the last one the file is checked and stored.
     * (In pieces, because the host's upload limit is far below a render's size.)
     */
    public function chunk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'upload' => ['required', 'regex:/^[a-f0-9]{32}$/'],
            'offset' => 'required|integer|min:0',
            'last' => 'required|boolean',
            'name' => 'required|string|max:200',
            'presentation' => 'nullable|integer|exists:presentations,id',
            'chunk' => 'required|file',
        ]);
        $disk = Storage::disk(PresentationAsset::DISK);
        $disk->makeDirectory(PresentationAsset::DIR.'/tmp');
        $path = $disk->path(PresentationAsset::DIR."/tmp/{$data['upload']}.part");

        if ((int) $data['offset'] === 0) {
            @unlink($path);
            foreach (glob(dirname($path).'/*.part') ?: [] as $stale) {   // uploads abandoned midway
                if (filemtime($stale) < time() - 86400) {
                    @unlink($stale);
                }
            }
        }
        $have = is_file($path) ? filesize($path) : 0;
        abort_unless($have === (int) $data['offset'], 409, 'The upload lost its place — please try again.');
        file_put_contents($path, file_get_contents($request->file('chunk')->getRealPath()), FILE_APPEND | LOCK_EX);
        clearstatcache(true, $path);

        if (filesize($path) > PresentationAsset::MAX_BYTES) {
            @unlink($path);
            abort(413, 'This file is larger than '.(PresentationAsset::MAX_BYTES / 1048576).' MB.');
        }
        if (! $request->boolean('last')) {
            return response()->json(['received' => filesize($path)]);
        }

        try {
            $asset = PresentationAsset::import($path, $data['name'], $data['presentation'] ?? null, move: true);
        } catch (InvalidArgumentException $e) {
            @unlink($path);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['asset' => $asset->toEditor()]);
    }

    /** The light preview and thumbnail the browser made of an image, or of one page of a PDF. */
    public function previews(Request $request, PresentationAsset $asset): JsonResponse
    {
        $data = $request->validate([
            'page' => 'required|integer|min:0|max:5000',
            'preview' => 'required|file|max:12288|mimetypes:image/jpeg,image/png,image/webp',
            'thumb' => 'required|file|max:2048|mimetypes:image/jpeg,image/png,image/webp',
            'meta' => 'nullable|string|max:400000',
        ]);
        foreach (['preview', 'thumb'] as $variant) {
            $path = PresentationAsset::previewPath($asset->hash, $variant, (int) $data['page']);
            $request->file($variant)->storeAs(dirname($path), basename($path), PresentationAsset::DISK);
        }

        $meta = $asset->meta ?? [];
        $sent = json_decode($data['meta'] ?? '', true) ?: [];
        if (is_array($sent['pages'] ?? null)) {   // a PDF's pages, as the browser read them (points)
            $meta['pages'] = array_map(
                fn ($p) => ['w' => round((float) ($p['w'] ?? 0), 2), 'h' => round((float) ($p['h'] ?? 0), 2)],
                array_slice(array_values($sent['pages']), 0, 5000),
            );
        }
        if (isset($sent['orientation'])) {   // a photograph's EXIF turn, 1–8
            $meta['orientation'] = max(1, min(8, (int) $sent['orientation']));
        }
        $meta['previews'] = array_values(array_unique([...($meta['previews'] ?? []), (int) $data['page']]));
        // the same file in a copied presentation is the same pixels
        PresentationAsset::where('hash', $asset->hash)->get()->each->update(['meta' => $meta]);

        return response()->json(['asset' => $asset->fresh()->toEditor()]);
    }

    /** An original, a preview or a thumbnail — only ever to a signed-in admin. */
    public function file(Request $request, string $hash, string $variant): Response
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $hash) && in_array($variant, ['original', 'preview', 'thumb'], true), 404);
        $asset = PresentationAsset::where('hash', $hash)->firstOrFail();
        $disk = Storage::disk(PresentationAsset::DISK);

        $path = $variant === 'original'
            ? $asset->originalPath()
            : PresentationAsset::previewPath($hash, $variant, (int) $request->query('page', 0));
        if ($variant !== 'original' && $asset->kind === 'image' && ! $disk->exists($path)) {
            $path = $asset->originalPath();   // a small image (a logo) is its own preview
        }
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Content-Type' => File::mimeType($disk->path($path)) ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            // named by its hash, so it never changes
            'Cache-Control' => 'max-age=31536000, immutable',
        ])->setPrivate();   // a file response is "public" unless told: these stay in the admin's own browser
    }

    public function destroyAsset(PresentationAsset $asset): Response
    {
        $asset->delete();

        return response()->noContent();
    }

    public function storePreset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:theme,layout', 'name' => 'required|string|max:120', 'data' => 'required|string|max:400000',
        ]);
        abort_unless(is_array(json_decode($data['data'], true)), 422, 'This could not be read.');

        return response()->json(['preset' => self::preset(PresentationPreset::create($data))]);
    }

    public function destroyPreset(PresentationPreset $preset): Response
    {
        abort_if($preset->type === 'brand', 404);
        $preset->delete();

        return response()->noContent();
    }

    public function saveBrand(Request $request): JsonResponse
    {
        $data = $request->validate(['data' => 'required|string|max:400000']);
        abort_unless(is_array(json_decode($data['data'], true)), 422, 'This could not be read.');
        PresentationPreset::brand()->update(['data' => $data['data']]);

        return response()->json(['saved' => true]);
    }

    private static function preset(PresentationPreset $preset): array
    {
        return ['id' => $preset->id, 'type' => $preset->type, 'name' => $preset->name, 'data' => json_decode($preset->data)];
    }

    /** As much per request as the host's PHP takes, with room for the form around it. */
    private static function chunkBytes(): int
    {
        $bytes = fn (string $v) => (int) $v * match (strtolower(substr(trim($v), -1))) {
            'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1,
        };
        $limits = array_filter([$bytes((string) ini_get('upload_max_filesize')), $bytes((string) ini_get('post_max_size'))]);

        return (int) max(256 * 1024, min(4 * 1048576, ($limits ? min($limits) : 2097152) - 262144));
    }
}
