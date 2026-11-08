<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arabic versions of the studio's own content — the third language of the
 * site (/ar), beside the English and the Kurdish that each record already
 * carries. Every column is optional: a record with no Arabic is shown in
 * English on the Arabic pages, never in Kurdish.
 */
return new class extends Migration
{
    /** table => [column => after, type] */
    private const COLUMNS = [
        'projects' => [
            'name_ar' => ['name_ku', 'string'],
            'typology_ar' => ['typology', 'string'],
            'location_ar' => ['location', 'string'],
            'desc_ar' => ['desc_ku', 'text'],
            'narrative_ar' => ['narrative_ku', 'text'],
        ],
        'testimonials' => [
            'quote_ar' => ['quote_ku', 'text'],
            'person_name_ar' => ['person_name_ku', 'string'],
            'person_role_ar' => ['person_role_ku', 'string'],
        ],
        'job_openings' => [
            'title_ar' => ['title_ku', 'string'],
            'location_ar' => ['location_ku', 'string'],
            'summary_ar' => ['summary_ku', 'text'],
            'description_ar' => ['description_ku', 'text'],
        ],
        'clients' => [
            'sub_ar' => ['sub_ku', 'string'],
        ],
        'greetings' => [
            'title_ar' => ['title_ku', 'string'],
            'text_ar' => ['text_ku', 'text'],
        ],
        'project_updates' => [
            'title_ar' => ['title_ku', 'string'],
            'note_ar' => ['note_ku', 'text'],
        ],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table, $columns) {
                foreach ($columns as $column => [$after, $type]) {
                    if (Schema::hasColumn($table, $column)) {
                        continue;
                    }
                    $col = $type === 'text' ? $t->text($column) : $t->string($column);
                    $col->nullable();
                    if (Schema::hasColumn($table, $after)) {
                        $col->after($after);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $existing = array_values(array_filter(array_keys($columns), fn ($c) => Schema::hasColumn($table, $c)));
            if ($existing) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($existing));
            }
        }
    }
};
