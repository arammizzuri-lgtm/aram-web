<?php

namespace App\Support;

use App\Models\Category;

/**
 * The fixed choices on the enquiry form, in both languages. Stored by key, so
 * a wording change here never orphans an enquiry already received.
 *
 * Budgets are in US dollars, the currency construction is priced in across
 * the Kurdistan Region.
 */
class Enquiry
{
    /** @return array<string, array{en: string, ku: string}> */
    public static function budgets(): array
    {
        return [
            'under-100k' => ['en' => 'Under $100,000', 'ku' => 'کەمتر لە ١٠٠ هەزار دۆلار'],
            '100-250k' => ['en' => '$100,000 – $250,000', 'ku' => '١٠٠ – ٢٥٠ هەزار دۆلار'],
            '250-500k' => ['en' => '$250,000 – $500,000', 'ku' => '٢٥٠ – ٥٠٠ هەزار دۆلار'],
            '500k-1m' => ['en' => '$500,000 – $1 million', 'ku' => '٥٠٠ هەزار – ١ ملیۆن دۆلار'],
            'over-1m' => ['en' => 'Over $1 million', 'ku' => 'زیاتر لە ١ ملیۆن دۆلار'],
            'unsure' => ['en' => 'Not sure yet', 'ku' => 'هێشتا دڵنیا نیم'],
        ];
    }

    /** @return array<string, array{en: string, ku: string}> */
    public static function timelines(): array
    {
        return [
            'asap' => ['en' => 'As soon as possible', 'ku' => 'بە زووترین کات'],
            '3m' => ['en' => 'Within 3 months', 'ku' => 'لە ماوەی ٣ مانگدا'],
            '6m' => ['en' => 'In 3 – 6 months', 'ku' => 'لە ماوەی ٣ – ٦ مانگدا'],
            '12m' => ['en' => 'In 6 – 12 months', 'ku' => 'لە ماوەی ٦ – ١٢ مانگدا'],
            'exploring' => ['en' => 'Just exploring', 'ku' => 'تەنها بیرکردنەوەیە'],
        ];
    }

    /**
     * Project types: the studio's own categories (so a category added in the
     * admin is offered here too), then "Other".
     *
     * @return array<string, array{en: string, ku: string}>
     */
    public static function types(): array
    {
        $types = [];
        foreach (Category::query()->ordered()->get(['key', 'name', 'name_ku']) as $c) {
            $types[$c->key] = ['en' => $c->name, 'ku' => $c->name_ku ?: $c->name];
        }

        return $types + ['other' => ['en' => 'Something else', 'ku' => 'شتێکی تر']];
    }

    /** One option's label in a language, or the stored value itself if unknown. */
    public static function label(array $options, ?string $key, string $lang = 'en'): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        return $options[$key][$lang] ?? $options[$key]['en'] ?? $key;
    }

    /**
     * key => English label, for admin selects and filters.
     *
     * @param  array<string, array{en: string, ku: string}>  $options
     * @return array<string, string>
     */
    public static function englishOptions(array $options): array
    {
        return array_map(fn ($o) => $o['en'], $options);
    }
}
