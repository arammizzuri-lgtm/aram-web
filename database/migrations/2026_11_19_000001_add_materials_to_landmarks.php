<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What each landmark is built of (App\Models\Landmark::MATERIALS), the main
 * material first: the heritage map filters and colours by it, and each page
 * names it. Each list follows what the landmark's own researched page says —
 * "basalt on limestone foundations" (Ain Dara), "fired brick over mudbrick"
 * (Erbil Citadel), "walls of fired brick and rubble faced with cut stone"
 * (Takht-e Soleyman). Set only where a landmark has none yet, so what the
 * admin has chosen since is kept.
 */
return new class extends Migration
{
    private const BUILT_OF = [
        'gobekli-tepe' => ['limestone'],
        'shanidar-cave' => ['rock'],
        'tell-brak' => ['mudbrick'],
        'urkesh' => ['mudbrick', 'stone'],
        'erbil-citadel' => ['brick', 'mudbrick', 'timber'],
        'tell-halaf' => ['basalt', 'limestone'],
        'ain-dara-temple' => ['basalt', 'limestone'],
        'jerwan-aqueduct' => ['limestone'],
        'khinis-reliefs' => ['rock'],
        'bisotun' => ['rock'],
        'mount-nemrut' => ['limestone', 'sandstone'],
        'taq-e-bostan' => ['rock'],
        'takht-e-soleyman' => ['brick', 'stone'],
        'amed-city-walls' => ['basalt', 'limestone'],
        'lalish' => ['limestone'],
        'mudhafaria-minaret' => ['brick'],
        'hasankeyf' => ['rock', 'stone', 'brick'],
        'mardin-old-town' => ['limestone'],
        'amedi' => ['stone', 'rock'],
        'ishak-pasha-palace' => ['stone'],
        'hawraman' => ['stone', 'timber'],
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('landmarks', 'materials')) {
            Schema::table('landmarks', function (Blueprint $table) {
                $table->json('materials')->nullable();
            });
        }

        foreach (self::BUILT_OF as $slug => $materials) {
            DB::table('landmarks')->where('slug', $slug)
                ->where(fn ($q) => $q->whereNull('materials')->orWhere('materials', '')->orWhere('materials', '[]'))
                ->update(['materials' => json_encode($materials)]);
        }
    }

    public function down(): void
    {
        Schema::table('landmarks', function (Blueprint $table) {
            $table->dropColumn('materials');
        });
    }
};
