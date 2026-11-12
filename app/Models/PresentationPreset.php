<?php

namespace App\Models;

use App\Support\SiteText;
use Illuminate\Database\Eloquent\Model;

/**
 * What presentations share: saved templates (type "theme"), saved page
 * layouts ("layout") and the studio's branding ("brand", one row). The data
 * is the editor's JSON, kept as sent.
 */
class PresentationPreset extends Model
{
    protected $fillable = ['type', 'name', 'data'];

    /**
     * The branding, made on first use from what the site already knows and
     * the two logos that ship with the code: the studio's, and Dehat
     * Construction's for the projects done together (off unless switched on
     * in a presentation).
     */
    public static function brand(): self
    {
        return static::where('type', 'brand')->first() ?? static::create([
            'type' => 'brand', 'name' => 'Branding',
            'data' => json_encode([
                'studio' => 'Aram Mizuri Architecture',
                'address' => SiteText::en('contact_map_addr'),
                'phone' => setting('contact_phone', '+964 (0) 782 445 4414'),
                'email' => setting('contact_email_new', 'Info@arammizuri.com'),
                'website' => 'arammizuri.com',
                'logo' => self::shippedLogo('logo-aram-mizuri.png', 'Aram Mizuri — logo'),
                'logoMono' => true,
                'partners' => [[
                    'id' => 'dehat', 'name' => 'Dehat Construction & Architecture',
                    'logo' => self::shippedLogo('logo-dehat.png', 'Dehat — logo'), 'mono' => true,
                ]],
                'defaults' => ['theme' => 'minimal-white', 'size' => 'A3', 'orient' => 'landscape'],
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }

    private static function shippedLogo(string $file, string $name): string
    {
        return PresentationAsset::import(resource_path('presentations/'.$file), $name)->hash;
    }
}
