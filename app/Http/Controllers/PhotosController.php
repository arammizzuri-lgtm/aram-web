<?php

namespace App\Http\Controllers;

use App\Support\Rooms;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Room by room — /photos, and /ku/photos: every photograph whose room the
 * studio has said (Manage → Photos by room), the same room from every
 * project side by side — all the kitchens, all the guest rooms. A room in
 * the address (?room=kitchen) shows that one alone; the page works without
 * JavaScript, and photos.js opens a photograph large.
 */
class PhotosController extends Controller
{
    public function index(Request $request): View
    {
        $photos = Rooms::photos();
        $counts = $photos->countBy('room');
        $rooms = collect(Rooms::KEYS)->filter(fn ($k) => $counts->has($k))->values();

        $room = (string) $request->query('room', '');
        if (! $rooms->contains($room)) {
            $room = '';
        }

        return view('photos', [
            'photos' => $room === '' ? $photos : $photos->where('room', $room)->values(),
            'total' => $photos->count(),
            'counts' => $counts,
            'rooms' => $rooms,
            'room' => $room,
        ]);
    }
}
