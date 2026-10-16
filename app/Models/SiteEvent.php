<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something a visitor did that loads no page: opened a project in the
 * homepage overlay, pressed a WhatsApp button, saved a project (the heart),
 * shared one, or worked out a cost estimate. Anonymous, like PageView — a
 * daily visitor hash, never who they are.
 */
class SiteEvent extends Model
{
    public const UPDATED_AT = null;

    public const PROJECT_OPEN = 'project_open';

    public const WHATSAPP_CLICK = 'whatsapp_click';

    /** A heart turned on (turning it off is not counted). */
    public const SAVE = 'save';

    /** A project or a saved list sent on: WhatsApp, the phone's share sheet, or a copied link. */
    public const SHARE = 'share';

    /** A cost estimate worked out (once per visit to the estimator). */
    public const ESTIMATE = 'estimate';

    public const TYPES = [self::PROJECT_OPEN, self::WHATSAPP_CLICK, self::SAVE, self::SHARE, self::ESTIMATE];

    /**
     * Where a button sits: the WhatsApp ones, and the hearts and share
     * buttons. Anything not listed here is not counted at all, so a new
     * data-wa / data-save-at / data-share-at value must be added.
     */
    public const PLACEMENTS = ['fab', 'contact', 'overlay', 'project', 'projects', 'grid', 'saved', 'menu', 'not-found'];

    protected $fillable = ['type', 'project_id', 'placement', 'source', 'visitor_hash', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
