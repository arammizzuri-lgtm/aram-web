<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stores public contact-form submissions so they appear in the admin inbox.
 * Validated server-side and rate-limited at the route to deter spam.
 */
class ContactController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'project' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:5000'],
        ]);

        ContactMessage::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'project_type' => $data['project'] ?? null,
            'message' => $data['message'] ?? '',
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['ok' => true]);
    }
}
