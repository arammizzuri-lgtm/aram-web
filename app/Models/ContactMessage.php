<?php

namespace App\Models;

use App\Enums\LeadStatus;
use App\Support\CostEstimate;
use App\Support\Enquiry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * ContactMessage
 *
 * An enquiry from the public form — who, how to reach them, what they want to
 * build, which project brought them and which they had saved — and, once it
 * is in the admin, where it stands (status) and what has been said (notes).
 */
class ContactMessage extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'project_type', 'city', 'plot_area', 'budget', 'timeline',
        'project_id', 'saved_projects', 'estimate', 'page', 'lang', 'source', 'message', 'status', 'notes', 'is_read', 'ip_address',
    ];

    protected $attributes = [
        'status' => 'new',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'plot_area' => 'integer',
        'saved_projects' => 'array',
        'estimate' => 'array',
        'status' => LeadStatus::class,
    ];

    /** The project page (or overlay) the visitor was looking at when they wrote. */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The projects they had saved (hearted) when they wrote, in their order;
     * any deleted since are left out.
     *
     * @return Collection<int, Project>
     */
    public function savedProjects(): Collection
    {
        $ids = array_map('intval', $this->saved_projects ?? []);
        if ($ids === []) {
            return collect();
        }
        $found = Project::query()->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $found[$id] ?? null)->filter()->values();
    }

    /** "$180,000 – $220,000 to build · design fee $9,000 – $17,600", from the estimator. */
    public function estimateLabel(): ?string
    {
        if (empty($this->estimate['construction'])) {
            return null;
        }

        return CostEstimate::usdRange($this->estimate['construction']).' to build'
            .(empty($this->estimate['fee']) ? '' : ' · design fee '.CostEstimate::usdRange($this->estimate['fee']));
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
