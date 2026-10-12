<?php

namespace App\Models;

use App\Enums\LeadStatus;
use App\Support\Enquiry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ContactMessage
 *
 * An enquiry from the public form — who, how to reach them, what they want to
 * build, which project brought them — and, once it is in the admin, where it
 * stands (status) and what has been said (notes).
 */
class ContactMessage extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'project_type', 'city', 'plot_area', 'budget', 'timeline',
        'project_id', 'page', 'lang', 'source', 'message', 'status', 'notes', 'is_read', 'ip_address',
    ];

    protected $attributes = [
        'status' => 'new',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'plot_area' => 'integer',
        'status' => LeadStatus::class,
    ];

    /** The project page (or overlay) the visitor was looking at when they wrote. */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function typeLabel(): ?string
    {
        return Enquiry::label(Enquiry::types(), $this->project_type);
    }

    public function budgetLabel(): ?string
    {
        return Enquiry::label(Enquiry::budgets(), $this->budget);
    }

    public function timelineLabel(): ?string
    {
        return Enquiry::label(Enquiry::timelines(), $this->timeline);
    }

    /** "1,200 m²", or null. */
    public function plotLabel(): ?string
    {
        return $this->plot_area ? number_format($this->plot_area).' m²' : null;
    }

    /** Open a WhatsApp chat with the person who wrote, if they gave a phone. */
    public function whatsappUrl(): ?string
    {
        $digits = phone_digits($this->phone);

        return $digits ? 'https://wa.me/'.$digits : null;
    }

    /** Call them, if they gave a phone. */
    public function telUrl(): ?string
    {
        $digits = phone_digits($this->phone);

        return $digits ? 'tel:+'.$digits : null;
    }
}
