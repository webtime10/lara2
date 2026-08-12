<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ideal Region: удаляем старые step1–step7 колонки, добавляем новую схему step1–step8.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $oldFields = [
        'step1_goroda',
        'step1_goroda_description',
        'step1_ozera',
        'step1_ozera_description',
        'step1_vodopady',
        'step1_vodopady_description',
        'step1_gory',
        'step1_gory_description',
        'step2_restorany',
        'step2_restorany_description',
        'step2_razvlecheniya',
        'step2_razvlecheniya_description',
        'step2_otdyh',
        'step2_otdyh_description',
        'step2_gulyat',
        'step2_gulyat_description',
        'step3_aktivnyi',
        'step3_aktivnyi_description',
        'step3_srednii',
        'step3_srednii_description',
        'step3_spokoinyi',
        'step3_spokoinyi_description',
        'step4_kultura',
        'step4_kultura_description',
        'step4_eda',
        'step4_eda_description',
        'step4_komfort',
        'step4_komfort_description',
        'step4_priroda',
        'step4_priroda_description',
        'step5_odin',
        'step5_odin_description',
        'step5_druzya',
        'step5_druzya_description',
        'step5_semya',
        'step5_semya_description',
        'step5_para',
        'step5_para_description',
        'step6_vkusnaya_eda',
        'step6_vkusnaya_eda_description',
        'step6_krasivye_vidy',
        'step6_krasivye_vidy_description',
        'step6_vpechatleniya',
        'step6_vpechatleniya_description',
        'step6_otdohnut',
        'step6_otdohnut_description',
        'step7_parki',
        'step7_parki_description',
        'step7_muzei',
        'step7_muzei_description',
        'step7_progulki',
        'step7_progulki_description',
        'step7_shopping_razvlecheniya',
        'step7_shopping_razvlecheniya_description',
    ];

    public function up(): void
    {
        $toDrop = array_values(array_filter(
            $this->oldFields,
            fn (string $field) => Schema::hasColumn('category_descriptions', $field)
        ));

        if ($toDrop !== []) {
            Schema::table('category_descriptions', function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }

        $fields = (array) config('ideal_region_category_fields.fields', []);

        foreach ($fields as $field) {
            if (! is_string($field) || Schema::hasColumn('category_descriptions', $field)) {
                continue;
            }

            Schema::table('category_descriptions', function (Blueprint $table) use ($field) {
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
        $fields = (array) config('ideal_region_category_fields.fields', []);
        $toDrop = array_values(array_filter(
            $fields,
            fn ($field) => is_string($field) && Schema::hasColumn('category_descriptions', $field)
        ));

        if ($toDrop !== []) {
            Schema::table('category_descriptions', function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }

        foreach ($this->oldFields as $field) {
            if (Schema::hasColumn('category_descriptions', $field)) {
                continue;
            }

            Schema::table('category_descriptions', function (Blueprint $table) use ($field) {
                if (str_ends_with($field, '_description')) {
                    $table->longText($field)->nullable();
                } else {
                    $table->string($field, 255)->nullable();
                }
            });
        }
    }
};
