<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Япония step6: швейцарские занятия → японские акценты поездки.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $oldStep6 = [
        'step6_peshie_progulki',
        'step6_peshie_progulki_description',
        'step6_gornye_lyzhi',
        'step6_gornye_lyzhi_description',
        'step6_panoramnye_poezda',
        'step6_panoramnye_poezda_description',
        'step6_ekskursii_muzei',
        'step6_ekskursii_muzei_description',
        'step6_gastronomiya',
        'step6_gastronomiya_description',
        'step6_spa_ozdorovlenie',
        'step6_spa_ozdorovlenie_description',
    ];

    /** @var list<string> */
    private array $newStep6 = [
        'step6_gastronomiya',
        'step6_gastronomiya_description',
        'step6_onseny',
        'step6_onseny_description',
        'step6_hramy_kultura',
        'step6_hramy_kultura_description',
        'step6_gorodskaya_zhizn',
        'step6_gorodskaya_zhizn_description',
        'step6_lyzhi_zimniy',
        'step6_lyzhi_zimniy_description',
        'step6_hiking_priroda',
        'step6_hiking_priroda_description',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('j_category_descriptions')) {
            return;
        }

        $toDrop = array_values(array_filter(
            $this->oldStep6,
            fn (string $field) => Schema::hasColumn('j_category_descriptions', $field)
        ));

        if ($toDrop !== []) {
            Schema::table('j_category_descriptions', function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }

        foreach ($this->newStep6 as $field) {
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
            $this->newStep6,
            fn (string $field) => Schema::hasColumn('j_category_descriptions', $field)
        ));

        if ($toDrop !== []) {
            Schema::table('j_category_descriptions', function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }

        foreach ($this->oldStep6 as $field) {
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
