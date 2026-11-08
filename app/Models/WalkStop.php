<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One stop of the Erbil architecture walk (/erbil-walk): a building or place,
 * its date, a short note on why it matters, its pin and a photograph — each
 * text in English, Kurdish and Arabic.
 */
class WalkStop extends Model
{
    protected $fillable = [
        'sort_order', 'name_en', 'name_ku', 'name_ar', 'built_en', 'built_ku', 'built_ar',
        'note_en', 'note_ku', 'note_ar', 'lat', 'lng', 'image', 'image_credit', 'is_published',
    ];

    protected $casts = ['is_published' => 'boolean', 'lat' => 'float', 'lng' => 'float', 'sort_order' => 'integer'];

    public function scopePublishedOrdered($query)
    {
        return $query->where('is_published', true)->orderBy('sort_order')->orderBy('id');
    }

    /** A text in a language (default: the page's), the English where none was written. */
    public function tr(string $field, ?string $lang = null): ?string
    {
        return bi_record($lang ?? site_lang(), $this->{$field.'_en'}, $this->{$field.'_ku'}, $this->{$field.'_ar'});
    }

    public function imageUrl(): ?string
    {
        return Project::resolveImage($this->image);
    }

    /** Distance to another stop in metres, along the ground (haversine). */
    public function metresTo(self $other): int
    {
        if ($this->lat === null || $other->lat === null) {
            return 0;
        }
        $r = 6371000;
        $dLat = deg2rad($other->lat - $this->lat);
        $dLng = deg2rad($other->lng - $this->lng);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($this->lat)) * cos(deg2rad($other->lat)) * sin($dLng / 2) ** 2;

        return (int) round(2 * $r * asin(min(1, sqrt($a))));
    }
}
