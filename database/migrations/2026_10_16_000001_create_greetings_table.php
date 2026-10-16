<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Holiday cards: for Newroz, Eid, the New Year — a greeting card visitors
 * make with one of the studio's projects and their own name, and send on
 * WhatsApp (/card). Each occasion is shown between its dates; Newroz and
 * the New Year come back on the same dates every year, the Eids move with
 * the moon and are given their dates each year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('greetings', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // for the admin: "Newroz"
            $table->string('title_en');                   // on the card: "Happy Newroz"
            $table->string('title_ku')->nullable();
            $table->text('text_en')->nullable();
            $table->text('text_ku')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('yearly')->default(false);    // the same dates every year
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        $now = now();
        $rows = [
            ['name' => 'New Year', 'title_en' => 'Happy New Year', 'title_ku' => 'ساڵی نوێتان پیرۆز بێت',
                'text_en' => 'Wishing you a year of good homes, good light and good company.',
                'text_ku' => 'ساڵێکی پڕ لە ماڵی خۆش، ڕووناکی و هاوڕێیەتیی باشتان بۆ بە ئاوات دەخوازین.',
                'starts_on' => '2026-12-26', 'ends_on' => '2027-01-03', 'yearly' => true, 'is_published' => true],
            ['name' => 'Newroz', 'title_en' => 'Happy Newroz', 'title_ku' => 'نەورۆزتان پیرۆز بێت',
                'text_en' => 'A new spring, a new day — may it bring new beginnings to your home.',
                'text_ku' => 'بەهارێکی نوێ، ڕۆژێکی نوێ — با سەرەتایەکی نوێ بۆ ماڵەکەتان بهێنێت.',
                'starts_on' => '2027-03-14', 'ends_on' => '2027-03-25', 'yearly' => true, 'is_published' => true],
            ['name' => 'Eid al-Fitr', 'title_en' => 'Eid Mubarak', 'title_ku' => 'جەژنتان پیرۆز بێت',
                'text_en' => 'Wishing you and your family a blessed Eid.',
                'text_ku' => 'جەژنێکی پڕ لە خێر و خۆشی بۆ خۆتان و خێزانەکەتان بە ئاوات دەخوازین.',
                'starts_on' => null, 'ends_on' => null, 'yearly' => false, 'is_published' => false],
            ['name' => 'Eid al-Adha', 'title_en' => 'Eid Mubarak', 'title_ku' => 'جەژنی قوربانتان پیرۆز بێت',
                'text_en' => 'Wishing you and your family a blessed Eid.',
                'text_ku' => 'جەژنێکی پڕ لە خێر و خۆشی بۆ خۆتان و خێزانەکەتان بە ئاوات دەخوازین.',
                'starts_on' => null, 'ends_on' => null, 'yearly' => false, 'is_published' => false],
        ];
        foreach ($rows as $row) {
            DB::table('greetings')->insert($row + ['created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('greetings');
    }
};
