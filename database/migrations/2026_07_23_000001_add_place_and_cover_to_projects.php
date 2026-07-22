<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Structured location (neighbourhood / city / country) plus a per-project card
 * cover choice and focal point. The single `location` string is kept and is now
 * composed from the three parts on save; the neighbourhood drives the label on
 * the project mini-map, and city / country feed the homepage statistics.
 * `cover` selects which image is the grid cover (defaults to the first), and
 * cover_x / cover_y are the focal point (%) used to frame it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('neighbourhood')->nullable()->after('location');
            $table->string('city')->nullable()->after('neighbourhood');
            $table->string('country')->nullable()->after('city');
            $table->string('cover')->nullable()->after('imgs');       // chosen cover image ref
            $table->unsignedTinyInteger('cover_x')->default(50)->after('cover');
            $table->unsignedTinyInteger('cover_y')->default(50)->after('cover_x');
        });

        // Back-fill the structured parts from the existing "City[ area], Country"
        // strings so nothing changes on the public site until they're edited.
        foreach (DB::table('projects')->get(['id', 'location']) as $row) {
            [$neighbourhood, $city, $country] = self::split((string) $row->location);
            DB::table('projects')->where('id', $row->id)->update([
                'neighbourhood' => $neighbourhood,
                'city' => $city,
                'country' => $country,
            ]);
        }
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} [neighbourhood, city, country] */
    private static function split(string $location): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $location)), fn ($p) => $p !== ''));
        $n = count($parts);

        if ($n >= 3) {
            return [$parts[0], $parts[1], $parts[$n - 1]];
        }
        if ($n === 2) {
            // "Erbil Old City" -> city "Erbil", neighbourhood "Old City"
            $words = preg_split('/\s+/', $parts[0]);
            if (count($words) > 1) {
                return [implode(' ', array_slice($words, 1)), $words[0], $parts[1]];
            }

            return [null, $parts[0], $parts[1]];
        }
        if ($n === 1) {
            return [null, $parts[0], null];
        }

        return [null, null, null];
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['neighbourhood', 'city', 'country', 'cover', 'cover_x', 'cover_y']);
        });
    }
};
