<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What you offered, and the record of what the customer agreed to.
 *
 * The document matters less than the snapshot. On approval the lines and their
 * photos are frozen; editing the deal afterwards supersedes this quotation
 * rather than altering it. Without that, "you approved this model" is your word
 * against theirs and the evidence is somewhere in a WhatsApp thread.
 *
 * `approved_by_name` is the customer's person, typed in. They never log in —
 * you record who told you yes, through which channel, and when.
 */
class Quotation extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'superseded' => 'Superseded',
    ];

    protected $fillable = [
        'deal_id', 'number', 'version', 'status', 'currency', 'exchange_rate',
        'total', 'total_base', 'quotation_date', 'valid_until', 'language',
        'terms', 'notes', 'sent_at',
        'approved_at', 'approved_by_name', 'approval_channel', 'approval_note',
        'recorded_by', 'rejected_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'exchange_rate' => 'decimal:6',
            'total' => 'decimal:4',
            'total_base' => 'decimal:4',
            'quotation_date' => 'date',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class)->orderBy('display_order');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRightToLeft(): bool
    {
        return $this->language === 'ckb';
    }

    public function hasExpired(): bool
    {
        return $this->valid_until !== null
            && $this->valid_until->isPast()
            && ! $this->isApproved();
    }
}
