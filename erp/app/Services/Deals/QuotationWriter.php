<?php

namespace App\Services\Deals;

use App\Models\Deal;
use App\Models\DealLine;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Services\Documents\DocumentNumberGenerator;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Building quotations, and recording what the customer actually agreed to.
 *
 * The document is the lesser half of this. The valuable half is the snapshot:
 * on approval the lines, prices and photos are copied and frozen, so editing
 * the deal afterwards cannot rewrite what was agreed.
 *
 * Without that, "you approved this model" is your word against theirs and the
 * evidence is somewhere in a WhatsApp thread.
 */
class QuotationWriter
{
    public function __construct(private readonly DocumentNumberGenerator $numbers) {}

    /**
     * Copy the deal's current lines into a new quotation version.
     *
     * Any existing draft or sent quotation is superseded rather than edited —
     * that is what leaves a visible trail of what changed and when. An approved
     * one is superseded too, but the approval record on it survives untouched.
     */
    public function build(Deal $deal): Quotation
    {
        $deal->loadMissing('lines', 'customer');

        if ($deal->lines->isEmpty()) {
            throw new RuntimeException('Add at least one item before quoting.');
        }

        return DB::transaction(function () use ($deal) {
            $version = (int) $deal->quotations()->max('version') + 1;

            $deal->quotations()
                ->whereIn('status', ['draft', 'sent'])
                ->update(['status' => 'superseded']);

            $quotation = Quotation::create([
                'deal_id' => $deal->id,
                'number' => $this->numbers->next('quotation'),
                'version' => $version,
                'status' => 'draft',
                'currency' => $deal->sell_currency,
                'exchange_rate' => $deal->rateFor($deal->sell_currency),
                'quotation_date' => today(),
                'valid_until' => today()->addDays(14),
                // Taken from the customer, so printing never asks a question.
                'language' => $deal->customer?->document_language ?? 'en',
            ]);

            $this->copyLines($deal, $quotation);

            return $quotation->refresh();
        });
    }

    /**
     * Frozen copies, not references.
     *
     * The duplication is the entire point. A reference would let a later edit
     * to the deal rewrite a document the customer already has. Cost is never
     * copied — a quotation must not carry purchase prices even in the row that
     * backs it.
     */
    private function copyLines(Deal $deal, Quotation $quotation): void
    {
        $total = Money::zero($quotation->currency);

        foreach ($deal->lines as $index => $line) {
            /** @var DealLine $line */
            $lineTotal = Money::of($line->unit_price, $quotation->currency)
                ->times($line->quantity);

            QuotationLine::create([
                'quotation_id' => $quotation->id,
                'deal_line_id' => $line->id,
                'description' => $line->description,
                'description_ku' => $line->description_ku,
                'specification' => $line->specification,
                'quantity' => $line->quantity,
                'unit' => $line->unit,
                'unit_price' => $line->unit_price,
                'line_total' => $lineTotal->amount,
                'photo_path' => $line->photo_path,
                'display_order' => $line->display_order ?: $index,
            ]);

            $total = $total->plus($lineTotal);
        }

        $quotation->update([
            'total' => $total->amount,
            'total_base' => $deal->toBase($total)->amount,
        ]);
    }

    public function markSent(Quotation $quotation): Quotation
    {
        $quotation->update(['status' => 'sent', 'sent_at' => now()]);
        $quotation->deal->update(['status' => 'quoted']);

        return $quotation->refresh();
    }

    /**
     * Record that the customer said yes.
     *
     * `approvedByName` is their person, typed in — they never log in, so what
     * is being recorded is who told you, through which channel, and when. That
     * is the evidence, and it is why none of it is inferred.
     */
    public function approve(
        Quotation $quotation,
        string $approvedByName,
        ?string $channel = null,
        ?string $note = null,
    ): Quotation {
        if ($quotation->status === 'superseded') {
            throw new RuntimeException('That quotation has been replaced by a newer version.');
        }

        return DB::transaction(function () use ($quotation, $approvedByName, $channel, $note) {
            $quotation->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by_name' => $approvedByName,
                'approval_channel' => $channel,
                'approval_note' => $note,
                'recorded_by' => auth()->id(),
            ]);

            /*
             * The deal carries the approval too, because that is what the
             * purchase screens check. A purchase created after this point is no
             * longer "at your own risk".
             *
             * The date is stamped directly and the stage goes through
             * DealProgress, which only moves forward. Approval is very often
             * recorded late — the goods are already bought, sometimes already
             * shipping — and setting the stage to `approved` outright would
             * wind such a deal backwards to a point it left weeks ago.
             */
            $quotation->deal->update(['approved_at' => now()]);

            app(DealProgress::class)->advanceTo($quotation->deal, 'approved');

            return $quotation->refresh();
        });
    }

    /**
     * Record that the customer said yes, with or without a quotation.
     *
     * `approve()` needs a quotation to approve, and a great many yeses do not
     * arrive against one — they arrive on WhatsApp, against a photo and a
     * price. Until now that left no way to clear a deal of "bought at your own
     * risk" short of raising a quotation after the fact for a decision that had
     * already been made.
     *
     * Where a live quotation does exist the approval goes onto it, because that
     * is where the evidence belongs: the frozen lines and photos are what "you
     * approved this model" points at. Where there is none, the deal carries it
     * alone.
     */
    public function recordApproval(
        Deal $deal,
        string $approvedByName,
        ?string $channel = null,
        ?string $note = null,
    ): Deal {
        $quotation = $deal->quotations()
            ->whereIn('status', ['draft', 'sent'])
            ->orderByDesc('version')
            ->first();

        if ($quotation instanceof Quotation) {
            $this->approve($quotation, $approvedByName, $channel, $note);

            return $deal->fresh();
        }

        return DB::transaction(function () use ($deal, $approvedByName, $channel, $note) {
            $deal->update([
                'approved_at' => now(),
                /*
                 * With no quotation to hold it, the evidence goes on the deal's
                 * own notes — who said yes and how. An approval nobody can
                 * point at afterwards is not worth recording.
                 */
                'internal_notes' => trim(implode("\n\n", array_filter([
                    $deal->internal_notes,
                    trim(sprintf(
                        'Approved by %s%s.%s',
                        $approvedByName,
                        $channel ? ' by '.str($channel)->replace('_', ' ') : '',
                        $note ? ' '.$note : '',
                    )),
                ]))) ?: null,
            ]);

            app(DealProgress::class)->advanceTo($deal, 'approved');

            return $deal->fresh();
        });
    }

    public function reject(Quotation $quotation, ?string $reason = null): Quotation
    {
        $quotation->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $quotation->refresh();
    }
}
