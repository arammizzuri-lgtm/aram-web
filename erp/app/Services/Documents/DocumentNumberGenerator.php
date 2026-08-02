<?php

namespace App\Services\Documents;

use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * Allocates human-readable document numbers such as PO-2026-0001.
 *
 * The counter lives in its own row and is locked for the duration of the
 * allocation, so two users confirming an order at the same moment cannot be
 * handed the same number. Scanning the documents table for the highest number
 * instead — the obvious approach — races badly and leaves duplicates behind.
 */
class DocumentNumberGenerator
{
    /**
     * Reserve and return the next number for a document type.
     *
     * Runs in its own transaction unless the caller already opened one, in
     * which case the lock is held until that outer transaction commits.
     */
    public function next(string $documentType, ?int $year = null, ?string $prefix = null): string
    {
        $year ??= (int) now()->format('Y');

        $allocate = function () use ($documentType, $year, $prefix): string {
            $sequence = NumberSequence::query()
                ->where('document_type', $documentType)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                $sequence = NumberSequence::create([
                    'document_type' => $documentType,
                    'year' => $year,
                    'prefix' => $prefix ?? $this->defaultPrefix($documentType),
                    'next_number' => 1,
                ]);
            }

            $number = $sequence->render($sequence->next_number);

            $sequence->increment('next_number');

            return $number;
        };

        return DB::transactionLevel() > 0
            ? $allocate()
            : DB::transaction($allocate);
    }

    /** Preview the next number without consuming it. */
    public function peek(string $documentType, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        $sequence = NumberSequence::query()
            ->where('document_type', $documentType)
            ->where('year', $year)
            ->first();

        return $sequence
            ? $sequence->render($sequence->next_number)
            : sprintf('%s-%d-%s', $this->defaultPrefix($documentType), $year, str_pad('1', 4, '0', STR_PAD_LEFT));
    }

    private function defaultPrefix(string $documentType): string
    {
        return strtoupper(collect(explode('_', $documentType))
            ->map(fn (string $word) => $word[0] ?? '')
            ->implode(''));
    }
}
