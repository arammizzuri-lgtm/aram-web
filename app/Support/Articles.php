<?php

namespace App\Support;

/**
 * The journal's essays: pieces written for the site itself, unlike the build
 * entries, which come from a project's progress in the admin. Each has a view
 * of its own under views/journal/ and is listed on /journal among the entries.
 * Texts are [English, Kurdish, Arabic], as bi() takes them.
 */
class Articles
{
    /** @return list<array{slug: string, view: string, date: string, kind: array, title: array, summary: array, cover: string}> */
    public static function all(): array
    {
        return [
            [
                'slug' => 'citadel-house-plans',
                'view' => 'journal.citadel-houses',
                'date' => '2026-11-15',
                'kind' => ['Essay', 'وتار', 'مقال'],
                'title' => ['The houses of the Citadel, in plan', 'خانووەکانی قەڵا، لە نەخشەدا', 'بيوت القلعة، في المسقط'],
                'summary' => [
                    'Seven houses and one whole block of Erbil Citadel, redrawn from the published surveys: twelve plans, and what they show about how a house was planned and built here.',
                    'حەوت خانوو و یەک بلۆکی تەواوی قەڵای هەولێر، لە ڕووپێوە بڵاوکراوەکانەوە دووبارە کێشراونەتەوە: دوازدە نەخشە، و ئەوەی دەیڵێن دەربارەی چۆنیەتی نەخشەدانان و دروستکردنی خانوو لێرە.',
                    'سبعة بيوت وبلوك كامل من قلعة أربيل، أُعيد رسمها عن المسوحات المنشورة: اثنا عشر مسقطاً، وما تكشفه عن طريقة تخطيط البيت وبنائه هنا.',
                ],
                'cover' => 'h318',   // a plan from resources/journal/citadel-plans.php
            ],
        ];
    }

    public static function find(string $slug): ?array
    {
        foreach (self::all() as $article) {
            if ($article['slug'] === $slug) {
                return $article;
            }
        }

        return null;
    }

    public static function url(array $article, ?string $lang = null): string
    {
        return site_url('/journal/'.$article['slug'], $lang);
    }
}
