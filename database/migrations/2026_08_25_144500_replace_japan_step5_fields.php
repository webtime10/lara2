<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Япония step5: швейцарские слоты → японские варианты природы/атмосферы.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $oldStep5 = [
        'step5_vysokogornye_alpy',
        'step5_vysokogornye_alpy_description',
        'step5_ozera_vodopady',
        'step5_ozera_vodopady_description',
        'step5_sredizemnomorskiy_vayb',
        'step5_sredizemnomorskiy_vayb_description',
        'step5_alpiyskie_luga',
        'step5_alpiyskie_luga_description',
        'step5_istoricheskie_goroda',
        'step5_istoricheskie_goroda_description',
        'step5_vinodelcheskie_terrasy',
        'step5_vinodelcheskie_terrasy_description',
    ];

    /** @var list<string> */
    private array $newStep5 = [
        'step5_traditsionnye_goroda',
        'step5_traditsionnye_goroda_description',
        'step5_gory_lesa_doliny',
        'step5_gory_lesa_doliny_description',
        'step5_more_plyazhi_ostrova',
        'step5_more_plyazhi_ostrova_description',
        'step5_vulkany_onseny',
        'step5_vulkany_onseny_description',
        'step5_ozera_vodopady_zelen',
        'step5_ozera_vodopady_zelen_description',
        'step5_megapolisy',
        'step5_megapolisy_description',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('j_category_descriptions')) {
            return;
        }

        $toDrop = array_values(array_filter(
            $this->oldStep5,
            fn (string $field) => Schema::hasColumn('j_category_descriptions', $field)
        ));

        if ($toDrop !== []) {
            Schema::table('j_category_descriptions', function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }

        foreach ($this->newStep5 as $field) {
            if (Schema::hasColumn('j_category_descriptions', $field)) {
                continue;
            }

            Schema::table('j_category_descriptions', function (Blueprint $table) use ($field) {
                if (str_ends_with($field, '_description')) {
                    $table->longText($field)->nullable();
                } else {
                    $table->string($field, 255)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('j_category_descriptions')) {
            return;
        }

        $toDrop = array_values(array_filter(
            $this->newStep5,
            fn (string $field) => Schema::hasColumn('j_category_descriptions', $field)
        ));

        if ($toDrop !== []) {
            Schema::table('j_category_descriptions', function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }

        foreach ($this->oldStep5 as $field) {
            if (Schema::hasColumn('j_category_descriptions', $field)) {
                continue;
            }

            Schema::table('j_category_descriptions', function (Blueprint $table) use ($field) {
                if (str_ends_with($field, '_description')) {
                    $table->longText($field)->nullable();
                } else {
                    $table->string($field, 255)->nullable();
                }
            });
        }
    }
};
