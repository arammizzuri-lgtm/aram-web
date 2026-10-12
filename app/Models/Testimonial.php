<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A client's words about the studio. Shown in the homepage quotes, and on the
 * page of the project it is about, if it names one.
 */
class Testimonial extends Model
{
    protected $fillable = [
        'quote_en', 'quote_ku', 'person_name', 'person_name_ku', 'person_role_en', 'person_role_ku',
        'client_id', 'project_id', 'sort_order', 'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Testimonial>  $query */
    public function scopePublishedOrdered($query)
    {
        return $query->where('is_published', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Only quotes that have something to say in this language. A quote is the
     * client's own words, so one written in English is never shown on the
     * Kurdish site in its place.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Testimonial>  $query
     */
    public function scopeInLanguage($query, string $lang)
    {
        $column = $lang === 'ku' ? 'quote_ku' : 'quote_en';

        return $query->whereNotNull($column)->where($column, '!=', '');
    }

    public function quote(string $lang): string
    {
        return (string) ($lang === 'ku' ? $this->quote_ku : $this->quote_en);
    }

    public function personName(string $lang): string
    {
        return $lang === 'ku' && filled($this->person_name_ku) ? $this->person_name_ku : $this->person_name;
    }

    /** "Owner, Shaqlawa Villa" — the role, then the client it speaks for. */
    public function attribution(string $lang): string
    {
        $role = $lang === 'ku' && filled($this->person_role_ku) ? $this->person_role_ku : $this->person_role_en;

        return collect([$role, $this->client?->name])->filter()->implode($lang === 'ku' ? '، ' : ', ');
    }
}
