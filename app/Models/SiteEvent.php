<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something a visitor did that loads no page: opened a project in the
 * homepage overlay, or pressed a WhatsApp button. Anonymous, like PageView —
 * a daily visitor hash, never who they are.
 */
class SiteEvent extends Model
{
    public const UPDATED_AT = null;

    public const PROJECT_OPEN = 'project_open';

    public const WHATSAPP_CLICK = 'whatsapp_click';

    /** The WhatsApp buttons, by where they sit. */
    public const PLACEMENTS = ['fab', 'contact', 'overlay', 'project'];

    protected $fillable = ['type', 'project_id', 'placement', 'source', 'visitor_hash', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
