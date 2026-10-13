<?php

namespace App\Http\Controllers;

use App\Models\RoomDrawing;
use App\Models\RoomPayment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * A client room's drawings and receipts, for the studio: registered inside
 * the panel's authenticated routes (AdminPanelProvider). The client reaches
 * the same files through their room (RoomController).
 */
class RoomFileController extends Controller
{
    public function drawing(RoomDrawing $drawing): Response
    {
        abort_unless($drawing->fileExists(), 404);

        return Storage::disk(RoomDrawing::DISK)->response($drawing->file_path, $drawing->downloadName(), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function receipt(RoomPayment $payment): Response
    {
        abort_unless($payment->receiptExists(), 404);

        return Storage::disk(RoomPayment::DISK)->response($payment->receipt_path, $payment->receipt_name ?: 'receipt', [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
