<?php

namespace App\Http\Controllers;

use App\Models\Project;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Site boards: a printable poster for the fence of a building going up —
 * its render, its name, and a QR code that opens its page — so the people
 * who pass it every day can see what it will be, and who designed it.
 *
 *   /b/{id}   what the QR code says: short, so the code is coarse enough to
 *             read from across a street. It sends the phone to the project's
 *             page — in Kurdish unless the phone is set to English — marked
 *             as come from a board (utm_source=board), which the dashboard
 *             counts as its own source.
 *   poster()  the poster itself, for the studio only (the panel's
 *             authenticated routes), in A3 to A0 and dark or light, to print
 *             or save as a PDF for the printer.
 */
class BoardController extends Controller
{
    public const SIZES = [
        'a3' => [297, 420], 'a2' => [420, 594], 'a1' => [594, 841], 'a0' => [841, 1189],
    ];

    public function scan(Request $request, int $id): RedirectResponse
    {
        $project = Project::query()->find($id);
        $lang = str_starts_with(strtolower((string) $request->getPreferredLanguage(['en', 'ckb', 'ku', 'ar'])), 'en') ? 'en' : 'ku';
        $target = $project && $project->hasPage() ? $project->pageUrl($lang) : site_url('/', $lang);

        return redirect()->away($target.'?utm_source=board', 302);
    }

    public function poster(Request $request, Project $project): View
    {
        $size = array_key_exists($request->query('size'), self::SIZES) ? $request->query('size') : 'a2';
        $theme = $request->query('theme') === 'light' ? 'light' : 'dark';
        $short = url('/b/'.$project->id);

        return view('board', [
            'project' => $project,
            'size' => $size,
            'mm' => self::SIZES[$size],
            'theme' => $theme,
            'short' => preg_replace('#^https?://(www\.)?#', '', $short),
            'qr' => self::qr($short),
            'photo' => $project->coverUrl(),
        ]);
    }

    /** A QR code as SVG, at the error correction a weathered print still reads at. */
    public static function qr(string $text): string
    {
        $options = new QROptions([
            'eccLevel' => EccLevel::Q,
            'outputBase64' => false,
            'drawLightModules' => false,
            'connectPaths' => true,
            'svgAddXmlHeader' => false,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
            'cssClass' => 'qr',
        ]);

        return (new QRCode($options))->render($text);
    }
}
