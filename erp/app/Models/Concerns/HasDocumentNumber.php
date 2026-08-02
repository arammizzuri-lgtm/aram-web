<?php

namespace App\Models\Concerns;

use App\Services\Documents\DocumentNumberGenerator;
use Illuminate\Database\Eloquent\Model;

/**
 * Assigns sequential, year-scoped document numbers such as PO-2026-0001.
 *
 * The number is only generated when one was not supplied, so imports and
 * manual overrides keep their own references.
 *
 * Allocation is delegated to DocumentNumberGenerator, which locks a counter
 * row rather than scanning this table for the highest existing number — two
 * users saving at the same instant would otherwise both read the same maximum
 * and produce the same number.
 */
trait HasDocumentNumber
{
    /** The short code that prefixes every number for this document type. */
    abstract public static function documentPrefix(): string;

    /** The key this model's counter is stored under, e.g. `purchase_order`. */
    public static function documentType(): string
    {
        return str(class_basename(static::class))->snake()->toString();
    }

    protected static function bootHasDocumentNumber(): void
    {
        static::creating(function (Model $model) {
            if (blank($model->number)) {
                $model->number = static::nextDocumentNumber();
            }
        });
    }

    public static function nextDocumentNumber(): string
    {
        return app(DocumentNumberGenerator::class)->next(
            static::documentType(),
            prefix: static::documentPrefix(),
        );
    }

    /** The number the next document would receive, without consuming it. */
    public static function peekDocumentNumber(): string
    {
        return app(DocumentNumberGenerator::class)->peek(static::documentType());
    }
}
