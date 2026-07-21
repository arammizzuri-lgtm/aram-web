<?php

use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store each project's map location on the record itself (lat/lng), replacing
 * the index-aligned coordinate array that lived in script.js. Existing
 * projects are backfilled from that same list (matched by display number) so
 * their map pins stay put.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->after('location');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
        });

        foreach (self::LEGACY_COORDS as $num => [$lat, $lng]) {
            Project::where('num', $num)
                ->whereNull('lat')
                ->update(['lat' => $lat, 'lng' => $lng]);
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng']);
        });
    }

    /** Display-number => [lat, lng], carried over from script.js PROJECT_COORDS. */
    private const LEGACY_COORDS = [
        '01' => [36.1912, 44.0092],
        '02' => [36.2018, 44.0278],
        '03' => [36.1983, 44.0088],
        '04' => [36.1718, 44.0465],
        '05' => [36.4073, 44.3224],
        '06' => [36.1958, 43.9988],
        '07' => [35.5616, 45.4322],
        '08' => [36.1785, 44.0152],
        '09' => [36.8663, 42.9816],
        '10' => [35.4676, 44.3922],
        '11' => [37.9144, 40.2306],
        '12' => [37.1444, 42.6849],
        '13' => [37.0861, 43.4889],
        '14' => [35.1803, 45.9864],
        '15' => [36.2574, 44.8789],
        '16' => [36.7439, 43.8904],
        '17' => [36.3199, 41.8596],
        '18' => [36.5133, 36.8688],
        '19' => [36.8932, 38.3533],
        '20' => [34.3142, 47.0650],
        '21' => [40.4168, -3.7038],
    ];
};
