<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ContactMessage
 *
 * A submission from the public contact form, readable in the admin inbox.
 */
class ContactMessage extends Model
{
    protected $fillable = [
        'name', 'email', 'project_type', 'message', 'is_read', 'ip_address',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];
}
