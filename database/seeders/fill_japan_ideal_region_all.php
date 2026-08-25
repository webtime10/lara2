<?php
/**
 * Fill Ideal Region scores + RU descriptions for all Japan (j_*) destinations.
 *
 * Usage:
 *   php database/seeders/fill_japan_ideal_region_all.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\JCategory;
use App\Models\JCategoryDescription;
use App\Models\Language;

const CONTENT_LANG = 'he';
const MANUFACTURER_ID = 2;

$scoreFields = scoreFieldKeys();
$optionTitles = (array) config('japan_ideal_region_category_fields.option_titles', []);
$defs = destinationDefinitions();

$language = Language::query()->where('code', CONTENT_LANG)->first();
if (! $language) {
    fwrite(STDERR, "Language '".CONTENT_LANG."' not found.\n");
    exit(1);
}

$filled = 0;
$skipped = 0;
$errors = 0;

$categories = JCategory::query()
    ->where('manufacturer_id', MANUFACTURER_ID)
    ->where('status', true)
    ->with(['descriptions' => static fn ($q) => $q->where('language_id', $language->id)])
    ->orderBy('id')
    ->get();

foreach ($categories as $category) {
    /** @var JCategoryDescription|null $desc */
    $desc = $category->descriptions->first();
    if (! $desc) {
        echo "[error] id={$category->id} — no description (".CONTENT_LANG.")\n";
        $errors++;
        continue;
    }

    $nameEn = (string) $desc->name;
    if (! isset($defs[$nameEn])) {
        echo "[error] id={$category->id} «{$nameEn}» — no definition\n";
        $errors++;
        continue;
    }

    $def = $defs[$nameEn];
    $nameRu = (string) $def['name_ru'];
    $tags = (array) ($def['tags'] ?? []);

    $scores = array_merge(
        templateScores((string) $def['template']),
        (array) ($def['scores'] ?? [])
    );

    foreach ($scoreFields as $field) {
        if (! array_key_exists($field, $scores)) {
            echo "[error] id={$category->id} missing score field {$field}\n";
            $errors++;
            continue 2;
        }
        $scores[$field] = (string) max(0, min(100, (int) $scores[$field]));
    }

    $descOverrides = (array) ($def['descriptions'] ?? []);
    $payload = [];

    // Keep existing intro HTML unless override provided.
    if (! empty($def['intro']) && is_string($def['intro'])) {
        $payload['description'] = $def['intro'];
    }

    foreach ($scoreFields as $field) {
        $payload[$field] = $scores[$field];
        $descKey = $field.'_description';
        if (isset($descOverrides[$field]) && is_string($descOverrides[$field]) && $descOverrides[$field] !== '') {
            $payload[$descKey] = $descOverrides[$field];
        } else {
            $payload[$descKey] = fallbackDescription(
                $field,
                (int) $scores[$field],
                $nameRu,
                $nameEn,
                $tags,
                $optionTitles[$field] ?? $field
            );
        }
    }

    try {
        $desc->fill($payload);
        $desc->save();
        echo "[ok] id={$category->id} {$nameEn}\n";
        $filled++;
    } catch (Throwable $e) {
        echo "[error] id={$category->id} {$nameEn}: {$e->getMessage()}\n";
        $errors++;
    }
}

echo "\nDone. filled={$filled} skipped={$skipped} errors={$errors}\n";
exit($errors > 0 ? 1 : 0);

// ---------------------------------------------------------------------------

function scoreFieldKeys(): array
{
    $fields = (array) config('japan_ideal_region_category_fields.fields', []);

    return array_values(array_filter(
        $fields,
        static fn ($f) => is_string($f) && str_starts_with($f, 'step') && ! str_ends_with($f, '_description')
    ));
}

function scoreBand(int $score): string
{
    if ($score >= 90) {
        return 'очень высокий';
    }
    if ($score >= 75) {
        return 'высокий';
    }
    if ($score >= 55) {
        return 'средний';
    }
    if ($score >= 35) {
        return 'умеренно-низкий';
    }

    return 'низкий';
}

function fieldThemeHint(string $field): string
{
    static $hints = [
        'step1_vesnoy' => 'весна (сакура, мягкая погода, начало сезона)',
        'step1_letom' => 'лето (фестивали, зелень, море/горы по региону)',
        'step1_osenyu' => 'осень (момдзи, прохладный комфорт, меньше жары)',
        'step1_zimoy' => 'зима (снег, иллюминации, онсэны, лыжи где уместно)',
        'step2_dnya_1_2' => 'короткий визит на 1–2 дня',
        'step2_dnya_3_4' => 'поездка на 3–4 дня',
        'step2_dney_5_7' => 'неделя на месте',
        'step2_dney_8_10' => 'длинный заезд 8–10 дней',
        'step2_bolee_10_dney' => 'очень долгий отдых 10+ дней',
        'step3_solo' => 'соло-путешествие',
        'step3_para' => 'поездка вдвоём',
        'step3_kompaniya_druzei' => 'компания друзей',
        'step3_semya_do_6' => 'семья с детьми до 6 лет',
        'step3_semya_ot_7' => 'семья с детьми от 7 лет',
        'step4_obshchestvennyy_transport' => 'поезда, метро, автобусы',
        'step4_arendovannyy_avtomobil' => 'арендованный автомобиль',
        'step4_sochetanie' => 'сочетание авто и общественного транспорта',
        'step5_traditsionnye_goroda' => 'традиционные города и старинная Япония',
        'step5_gory_lesa_doliny' => 'горы, леса и долины',
        'step5_more_plyazhi_ostrova' => 'море, пляжи и острова',
        'step5_vulkany_onseny' => 'вулканы и горячие источники',
        'step5_ozera_vodopady_zelen' => 'озёра, водопады и зелёные пейзажи',
        'step5_megapolisy' => 'мегаполисы и современная Япония',
        'step6_gastronomiya' => 'гастрономия и местная кухня',
        'step6_onseny' => 'онсэны и отдых в горячих источниках',
        'step6_hramy_kultura' => 'храмы, традиционная культура и история',
        'step6_gorodskaya_zhizn' => 'городская жизнь, шопинг и развлечения',
        'step6_lyzhi_zimniy' => 'лыжи, сноуборд и зимний отдых',
        'step6_hiking_priroda' => 'хайкинг и активный отдых на природе',
        'step7_byudzhetnyy' => 'бюджетный ценовой формат',
        'step7_standartnyy' => 'стандартный ценовой формат',
        'step7_povyshennyy_komfort' => 'повышенный комфорт',
        'step7_premialnyy' => 'премиальный формат',
        'step8_legkiy_marshrut' => 'лёгкий маршрут без сложных подходов',
        'step8_vizitnye_kartochki' => 'главные визитные карточки Японии',
        'step8_zashchita_ot_nepogody' => 'занятия при непогоде',
        'step8_uedinennost' => 'уединение и тишина',
        'step8_prostaya_logistika' => 'простая логистика для новичка',
        'step8_net_pozhelaniy' => 'универсальность без жёстких фильтров',
    ];

    return $hints[$field] ?? 'этот критерий';
}

function fallbackDescription(
    string $field,
    int $score,
    string $nameRu,
    string $nameEn,
    array $tags,
    string $optionTitle
): string {
    $band = scoreBand($score);
    $theme = fieldThemeHint($field);
    $tagLine = $tags !== [] ? (' Контекст: '.implode(', ', array_slice($tags, 0, 4)).'.') : '';

    if ($score >= 75) {
        return "В {$nameRu} ({$nameEn}) критерий «{$optionTitle}» оценивается как {$band} ({$score}/100): {$theme} — сильная сторона направления.{$tagLine}";
    }
    if ($score >= 45) {
        return "Для {$nameRu} «{$optionTitle}» — скорее средний результат ({$score}/100): {$theme} присутствует, но не является главной визиткой.{$tagLine}";
    }

    return "В {$nameRu} критерий «{$optionTitle}» оценивается низко ({$score}/100): {$theme} здесь слабо выражена или требует выезда.{$tagLine}";
}

/**
 * @return array<string,int>
 */
function neutralBase(): array
{
    return [
        'step1_vesnoy' => 82,
        'step1_letom' => 78,
        'step1_osenyu' => 88,
        'step1_zimoy' => 70,
        'step2_dnya_1_2' => 78,
        'step2_dnya_3_4' => 90,
        'step2_dney_5_7' => 86,
        'step2_dney_8_10' => 62,
        'step2_bolee_10_dney' => 42,
        'step3_solo' => 84,
        'step3_para' => 88,
        'step3_kompaniya_druzei' => 80,
        'step3_semya_do_6' => 72,
        'step3_semya_ot_7' => 82,
        'step4_obshchestvennyy_transport' => 88,
        'step4_arendovannyy_avtomobil' => 55,
        'step4_sochetanie' => 80,
        'step5_traditsionnye_goroda' => 55,
        'step5_gory_lesa_doliny' => 50,
        'step5_more_plyazhi_ostrova' => 35,
        'step5_vulkany_onseny' => 45,
        'step5_ozera_vodopady_zelen' => 50,
        'step5_megapolisy' => 40,
        'step6_gastronomiya' => 78,
        'step6_onseny' => 45,
        'step6_hramy_kultura' => 55,
        'step6_gorodskaya_zhizn' => 50,
        'step6_lyzhi_zimniy' => 25,
        'step6_hiking_priroda' => 55,
        'step7_byudzhetnyy' => 55,
        'step7_standartnyy' => 85,
        'step7_povyshennyy_komfort' => 78,
        'step7_premialnyy' => 62,
        'step8_legkiy_marshrut' => 80,
        'step8_vizitnye_kartochki' => 75,
        'step8_zashchita_ot_nepogody' => 72,
        'step8_uedinennost' => 48,
        'step8_prostaya_logistika' => 82,
        'step8_net_pozhelaniy' => 82,
    ];
}

/**
 * @return array<string,int>
 */
function templateScores(string $template): array
{
    $base = neutralBase();

    $map = [
        'megacity' => [
            'step1_vesnoy' => 88, 'step1_letom' => 82, 'step1_osenyu' => 90, 'step1_zimoy' => 78,
            'step2_dnya_1_2' => 92, 'step2_dnya_3_4' => 96, 'step2_dney_5_7' => 90, 'step2_dney_8_10' => 70, 'step2_bolee_10_dney' => 48,
            'step3_solo' => 94, 'step3_para' => 90, 'step3_kompaniya_druzei' => 88, 'step3_semya_do_6' => 78, 'step3_semya_ot_7' => 86,
            'step4_obshchestvennyy_transport' => 98, 'step4_arendovannyy_avtomobil' => 28, 'step4_sochetanie' => 55,
            'step5_traditsionnye_goroda' => 55, 'step5_gory_lesa_doliny' => 25, 'step5_more_plyazhi_ostrova' => 30,
            'step5_vulkany_onseny' => 22, 'step5_ozera_vodopady_zelen' => 28, 'step5_megapolisy' => 98,
            'step6_gastronomiya' => 96, 'step6_onseny' => 25, 'step6_hramy_kultura' => 62,
            'step6_gorodskaya_zhizn' => 98, 'step6_lyzhi_zimniy' => 18, 'step6_hiking_priroda' => 35,
            'step7_byudzhetnyy' => 48, 'step7_standartnyy' => 85, 'step7_povyshennyy_komfort' => 90, 'step7_premialnyy' => 88,
            'step8_legkiy_marshrut' => 95, 'step8_vizitnye_kartochki' => 94, 'step8_zashchita_ot_nepogody' => 96,
            'step8_uedinennost' => 18, 'step8_prostaya_logistika' => 96, 'step8_net_pozhelaniy' => 92,
        ],
        'traditional_city' => [
            'step1_vesnoy' => 96, 'step1_letom' => 72, 'step1_osenyu' => 95, 'step1_zimoy' => 70,
            'step2_dnya_1_2' => 85, 'step2_dnya_3_4' => 96, 'step2_dney_5_7' => 92, 'step2_dney_8_10' => 72, 'step2_bolee_10_dney' => 48,
            'step3_solo' => 90, 'step3_para' => 95, 'step3_kompaniya_druzei' => 78, 'step3_semya_do_6' => 75, 'step3_semya_ot_7' => 85,
            'step4_obshchestvennyy_transport' => 92, 'step4_arendovannyy_avtomobil' => 45, 'step4_sochetanie' => 78,
            'step5_traditsionnye_goroda' => 98, 'step5_gory_lesa_doliny' => 48, 'step5_more_plyazhi_ostrova' => 15,
            'step5_vulkany_onseny' => 35, 'step5_ozera_vodopady_zelen' => 55, 'step5_megapolisy' => 28,
            'step6_gastronomiya' => 90, 'step6_onseny' => 42, 'step6_hramy_kultura' => 96,
            'step6_gorodskaya_zhizn' => 55, 'step6_lyzhi_zimniy' => 12, 'step6_hiking_priroda' => 55,
            'step7_byudzhetnyy' => 50, 'step7_standartnyy' => 86, 'step7_povyshennyy_komfort' => 88, 'step7_premialnyy' => 80,
            'step8_legkiy_marshrut' => 88, 'step8_vizitnye_kartochki' => 96, 'step8_zashchita_ot_nepogody' => 82,
            'step8_uedinennost' => 42, 'step8_prostaya_logistika' => 88, 'step8_net_pozhelaniy' => 88,
        ],
        'temple_heritage' => [
            'step1_vesnoy' => 94, 'step1_letom' => 70, 'step1_osenyu' => 92, 'step1_zimoy' => 62,
            'step2_dnya_1_2' => 94, 'step2_dnya_3_4' => 88, 'step2_dney_5_7' => 65, 'step2_dney_8_10' => 35, 'step2_bolee_10_dney' => 18,
            'step3_solo' => 88, 'step3_para' => 92, 'step3_kompaniya_druzei' => 72, 'step3_semya_do_6' => 78, 'step3_semya_ot_7' => 85,
            'step4_obshchestvennyy_transport' => 90, 'step4_arendovannyy_avtomobil' => 50, 'step4_sochetanie' => 80,
            'step5_traditsionnye_goroda' => 92, 'step5_gory_lesa_doliny' => 60, 'step5_more_plyazhi_ostrova' => 25,
            'step5_vulkany_onseny' => 28, 'step5_ozera_vodopady_zelen' => 72, 'step5_megapolisy' => 18,
            'step6_gastronomiya' => 72, 'step6_onseny' => 30, 'step6_hramy_kultura' => 98,
            'step6_gorodskaya_zhizn' => 28, 'step6_lyzhi_zimniy' => 8, 'step6_hiking_priroda' => 70,
            'step7_byudzhetnyy' => 58, 'step7_standartnyy' => 88, 'step7_povyshennyy_komfort' => 75, 'step7_premialnyy' => 55,
            'step8_legkiy_marshrut' => 90, 'step8_vizitnye_kartochki' => 94, 'step8_zashchita_ot_nepogody' => 70,
            'step8_uedinennost' => 58, 'step8_prostaya_logistika' => 88, 'step8_net_pozhelaniy' => 82,
        ],
        'onsen_resort' => [
            'step1_vesnoy' => 88, 'step1_letom' => 75, 'step1_osenyu' => 92, 'step1_zimoy' => 90,
            'step2_dnya_1_2' => 82, 'step2_dnya_3_4' => 96, 'step2_dney_5_7' => 88, 'step2_dney_8_10' => 62, 'step2_bolee_10_dney' => 38,
            'step3_solo' => 82, 'step3_para' => 96, 'step3_kompaniya_druzei' => 70, 'step3_semya_do_6' => 75, 'step3_semya_ot_7' => 82,
            'step4_obshchestvennyy_transport' => 78, 'step4_arendovannyy_avtomobil' => 72, 'step4_sochetanie' => 88,
            'step5_traditsionnye_goroda' => 55, 'step5_gory_lesa_doliny' => 78, 'step5_more_plyazhi_ostrova' => 22,
            'step5_vulkany_onseny' => 98, 'step5_ozera_vodopady_zelen' => 72, 'step5_megapolisy' => 15,
            'step6_gastronomiya' => 80, 'step6_onseny' => 99, 'step6_hramy_kultura' => 48,
            'step6_gorodskaya_zhizn' => 25, 'step6_lyzhi_zimniy' => 35, 'step6_hiking_priroda' => 75,
            'step7_byudzhetnyy' => 42, 'step7_standartnyy' => 80, 'step7_povyshennyy_komfort' => 92, 'step7_premialnyy' => 88,
            'step8_legkiy_marshrut' => 85, 'step8_vizitnye_kartochki' => 86, 'step8_zashchita_ot_nepogody' => 95,
            'step8_uedinennost' => 68, 'step8_prostaya_logistika' => 78, 'step8_net_pozhelaniy' => 82,
        ],
        'ski_powder' => [
            'step1_vesnoy' => 55, 'step1_letom' => 70, 'step1_osenyu' => 62, 'step1_zimoy' => 98,
            'step2_dnya_1_2' => 45, 'step2_dnya_3_4' => 78, 'step2_dney_5_7' => 96, 'step2_dney_8_10' => 92, 'step2_bolee_10_dney' => 78,
            'step3_solo' => 80, 'step3_para' => 90, 'step3_kompaniya_druzei' => 94, 'step3_semya_do_6' => 62, 'step3_semya_ot_7' => 85,
            'step4_obshchestvennyy_transport' => 55, 'step4_arendovannyy_avtomobil' => 88, 'step4_sochetanie' => 90,
            'step5_traditsionnye_goroda' => 22, 'step5_gory_lesa_doliny' => 92, 'step5_more_plyazhi_ostrova' => 12,
            'step5_vulkany_onseny' => 55, 'step5_ozera_vodopady_zelen' => 70, 'step5_megapolisy' => 12,
            'step6_gastronomiya' => 72, 'step6_onseny' => 70, 'step6_hramy_kultura' => 25,
            'step6_gorodskaya_zhizn' => 35, 'step6_lyzhi_zimniy' => 99, 'step6_hiking_priroda' => 80,
            'step7_byudzhetnyy' => 35, 'step7_standartnyy' => 72, 'step7_povyshennyy_komfort' => 90, 'step7_premialnyy' => 92,
            'step8_legkiy_marshrut' => 65, 'step8_vizitnye_kartochki' => 82, 'step8_zashchita_ot_nepogody' => 70,
            'step8_uedinennost' => 58, 'step8_prostaya_logistika' => 65, 'step8_net_pozhelaniy' => 75,
        ],
        'alpine_hiking' => [
            'step1_vesnoy' => 75, 'step1_letom' => 94, 'step1_osenyu' => 92, 'step1_zimoy' => 72,
            'step2_dnya_1_2' => 60, 'step2_dnya_3_4' => 88, 'step2_dney_5_7' => 94, 'step2_dney_8_10' => 78, 'step2_bolee_10_dney' => 55,
            'step3_solo' => 88, 'step3_para' => 90, 'step3_kompaniya_druzei' => 85, 'step3_semya_do_6' => 65, 'step3_semya_ot_7' => 88,
            'step4_obshchestvennyy_transport' => 70, 'step4_arendovannyy_avtomobil' => 88, 'step4_sochetanie' => 92,
            'step5_traditsionnye_goroda' => 55, 'step5_gory_lesa_doliny' => 96, 'step5_more_plyazhi_ostrova' => 12,
            'step5_vulkany_onseny' => 48, 'step5_ozera_vodopady_zelen' => 88, 'step5_megapolisy' => 12,
            'step6_gastronomiya' => 72, 'step6_onseny' => 55, 'step6_hramy_kultura' => 55,
            'step6_gorodskaya_zhizn' => 22, 'step6_lyzhi_zimniy' => 70, 'step6_hiking_priroda' => 97,
            'step7_byudzhetnyy' => 52, 'step7_standartnyy' => 85, 'step7_povyshennyy_komfort' => 80, 'step7_premialnyy' => 65,
            'step8_legkiy_marshrut' => 62, 'step8_vizitnye_kartochki' => 78, 'step8_zashchita_ot_nepogody' => 55,
            'step8_uedinennost' => 75, 'step8_prostaya_logistika' => 68, 'step8_net_pozhelaniy' => 80,
        ],
        'coastal_town' => [
            'step1_vesnoy' => 88, 'step1_letom' => 85, 'step1_osenyu' => 90, 'step1_zimoy' => 62,
            'step2_dnya_1_2' => 90, 'step2_dnya_3_4' => 92, 'step2_dney_5_7' => 75, 'step2_dney_8_10' => 45, 'step2_bolee_10_dney' => 25,
            'step3_solo' => 86, 'step3_para' => 92, 'step3_kompaniya_druzei' => 78, 'step3_semya_do_6' => 80, 'step3_semya_ot_7' => 85,
            'step4_obshchestvennyy_transport' => 85, 'step4_arendovannyy_avtomobil' => 70, 'step4_sochetanie' => 88,
            'step5_traditsionnye_goroda' => 65, 'step5_gory_lesa_doliny' => 45, 'step5_more_plyazhi_ostrova' => 88,
            'step5_vulkany_onseny' => 30, 'step5_ozera_vodopady_zelen' => 70, 'step5_megapolisy' => 28,
            'step6_gastronomiya' => 90, 'step6_onseny' => 35, 'step6_hramy_kultura' => 55,
            'step6_gorodskaya_zhizn' => 48, 'step6_lyzhi_zimniy' => 10, 'step6_hiking_priroda' => 65,
            'step7_byudzhetnyy' => 58, 'step7_standartnyy' => 88, 'step7_povyshennyy_komfort' => 78, 'step7_premialnyy' => 60,
            'step8_legkiy_marshrut' => 90, 'step8_vizitnye_kartochki' => 82, 'step8_zashchita_ot_nepogody' => 68,
            'step8_uedinennost' => 55, 'step8_prostaya_logistika' => 85, 'step8_net_pozhelaniy' => 84,
        ],
        'island_beach' => [
            'step1_vesnoy' => 82, 'step1_letom' => 96, 'step1_osenyu' => 88, 'step1_zimoy' => 55,
            'step2_dnya_1_2' => 45, 'step2_dnya_3_4' => 78, 'step2_dney_5_7' => 94, 'step2_dney_8_10' => 90, 'step2_bolee_10_dney' => 78,
            'step3_solo' => 78, 'step3_para' => 94, 'step3_kompaniya_druzei' => 88, 'step3_semya_do_6' => 88, 'step3_semya_ot_7' => 90,
            'step4_obshchestvennyy_transport' => 55, 'step4_arendovannyy_avtomobil' => 92, 'step4_sochetanie' => 88,
            'step5_traditsionnye_goroda' => 35, 'step5_gory_lesa_doliny' => 40, 'step5_more_plyazhi_ostrova' => 99,
            'step5_vulkany_onseny' => 25, 'step5_ozera_vodopady_zelen' => 55, 'step5_megapolisy' => 22,
            'step6_gastronomiya' => 85, 'step6_onseny' => 20, 'step6_hramy_kultura' => 40,
            'step6_gorodskaya_zhizn' => 40, 'step6_lyzhi_zimniy' => 2, 'step6_hiking_priroda' => 75,
            'step7_byudzhetnyy' => 45, 'step7_standartnyy' => 82, 'step7_povyshennyy_komfort' => 88, 'step7_premialnyy' => 80,
            'step8_legkiy_marshrut' => 85, 'step8_vizitnye_kartochki' => 80, 'step8_zashchita_ot_nepogody' => 55,
            'step8_uedinennost' => 65, 'step8_prostaya_logistika' => 70, 'step8_net_pozhelaniy' => 82,
        ],
        'art_island' => [
            'step1_vesnoy' => 88, 'step1_letom' => 82, 'step1_osenyu' => 90, 'step1_zimoy' => 48,
            'step2_dnya_1_2' => 92, 'step2_dnya_3_4' => 88, 'step2_dney_5_7' => 55, 'step2_dney_8_10' => 25, 'step2_bolee_10_dney' => 12,
            'step3_solo' => 92, 'step3_para' => 94, 'step3_kompaniya_druzei' => 75, 'step3_semya_do_6' => 55, 'step3_semya_ot_7' => 70,
            'step4_obshchestvennyy_transport' => 70, 'step4_arendovannyy_avtomobil' => 40, 'step4_sochetanie' => 65,
            'step5_traditsionnye_goroda' => 35, 'step5_gory_lesa_doliny' => 30, 'step5_more_plyazhi_ostrova' => 85,
            'step5_vulkany_onseny' => 15, 'step5_ozera_vodopady_zelen' => 55, 'step5_megapolisy' => 20,
            'step6_gastronomiya' => 70, 'step6_onseny' => 15, 'step6_hramy_kultura' => 55,
            'step6_gorodskaya_zhizn' => 45, 'step6_lyzhi_zimniy' => 2, 'step6_hiking_priroda' => 55,
            'step7_byudzhetnyy' => 40, 'step7_standartnyy' => 75, 'step7_povyshennyy_komfort' => 88, 'step7_premialnyy' => 85,
            'step8_legkiy_marshrut' => 80, 'step8_vizitnye_kartochki' => 86, 'step8_zashchita_ot_nepogody' => 88,
            'step8_uedinennost' => 72, 'step8_prostaya_logistika' => 68, 'step8_net_pozhelaniy' => 78,
        ],
        'regional_hub' => [
            'step1_vesnoy' => 85, 'step1_letom' => 80, 'step1_osenyu' => 88, 'step1_zimoy' => 72,
            'step2_dnya_1_2' => 88, 'step2_dnya_3_4' => 92, 'step2_dney_5_7' => 82, 'step2_dney_8_10' => 55, 'step2_bolee_10_dney' => 35,
            'step3_solo' => 88, 'step3_para' => 86, 'step3_kompaniya_druzei' => 82, 'step3_semya_do_6' => 78, 'step3_semya_ot_7' => 84,
            'step4_obshchestvennyy_transport' => 90, 'step4_arendovannyy_avtomobil' => 70, 'step4_sochetanie' => 88,
            'step5_traditsionnye_goroda' => 55, 'step5_gory_lesa_doliny' => 45, 'step5_more_plyazhi_ostrova' => 40,
            'step5_vulkany_onseny' => 40, 'step5_ozera_vodopady_zelen' => 48, 'step5_megapolisy' => 72,
            'step6_gastronomiya' => 88, 'step6_onseny' => 40, 'step6_hramy_kultura' => 55,
            'step6_gorodskaya_zhizn' => 82, 'step6_lyzhi_zimniy' => 28, 'step6_hiking_priroda' => 50,
            'step7_byudzhetnyy' => 58, 'step7_standartnyy' => 88, 'step7_povyshennyy_komfort' => 80, 'step7_premialnyy' => 65,
            'step8_legkiy_marshrut' => 90, 'step8_vizitnye_kartochki' => 72, 'step8_zashchita_ot_nepogody' => 85,
            'step8_uedinennost' => 35, 'step8_prostaya_logistika' => 92, 'step8_net_pozhelaniy' => 88,
        ],
        'volcano_south' => [
            'step1_vesnoy' => 88, 'step1_letom' => 78, 'step1_osenyu' => 90, 'step1_zimoy' => 58,
            'step2_dnya_1_2' => 75, 'step2_dnya_3_4' => 92, 'step2_dney_5_7' => 88, 'step2_dney_8_10' => 62, 'step2_bolee_10_dney' => 40,
            'step3_solo' => 85, 'step3_para' => 88, 'step3_kompaniya_druzei' => 82, 'step3_semya_do_6' => 75, 'step3_semya_ot_7' => 85,
            'step4_obshchestvennyy_transport' => 75, 'step4_arendovannyy_avtomobil' => 88, 'step4_sochetanie' => 90,
            'step5_traditsionnye_goroda' => 48, 'step5_gory_lesa_doliny' => 75, 'step5_more_plyazhi_ostrova' => 55,
            'step5_vulkany_onseny' => 96, 'step5_ozera_vodopady_zelen' => 70, 'step5_megapolisy' => 35,
            'step6_gastronomiya' => 88, 'step6_onseny' => 85, 'step6_hramy_kultura' => 55,
            'step6_gorodskaya_zhizn' => 55, 'step6_lyzhi_zimniy' => 8, 'step6_hiking_priroda' => 85,
            'step7_byudzhetnyy' => 55, 'step7_standartnyy' => 86, 'step7_povyshennyy_komfort' => 80, 'step7_premialnyy' => 65,
            'step8_legkiy_marshrut' => 75, 'step8_vizitnye_kartochki' => 80, 'step8_zashchita_ot_nepogody' => 72,
            'step8_uedinennost' => 55, 'step8_prostaya_logistika' => 78, 'step8_net_pozhelaniy' => 82,
        ],
        'daytrip_castle' => [
            'step1_vesnoy' => 90, 'step1_letom' => 78, 'step1_osenyu' => 88, 'step1_zimoy' => 60,
            'step2_dnya_1_2' => 98, 'step2_dnya_3_4' => 70, 'step2_dney_5_7' => 35, 'step2_dney_8_10' => 15, 'step2_bolee_10_dney' => 8,
            'step3_solo' => 88, 'step3_para' => 90, 'step3_kompaniya_druzei' => 78, 'step3_semya_do_6' => 82, 'step3_semya_ot_7' => 88,
            'step4_obshchestvennyy_transport' => 92, 'step4_arendovannyy_avtomobil' => 55, 'step4_sochetanie' => 80,
            'step5_traditsionnye_goroda' => 95, 'step5_gory_lesa_doliny' => 25, 'step5_more_plyazhi_ostrova' => 12,
            'step5_vulkany_onseny' => 15, 'step5_ozera_vodopady_zelen' => 35, 'step5_megapolisy' => 25,
            'step6_gastronomiya' => 70, 'step6_onseny' => 15, 'step6_hramy_kultura' => 92,
            'step6_gorodskaya_zhizn' => 35, 'step6_lyzhi_zimniy' => 5, 'step6_hiking_priroda' => 35,
            'step7_byudzhetnyy' => 65, 'step7_standartnyy' => 88, 'step7_povyshennyy_komfort' => 70, 'step7_premialnyy' => 48,
            'step8_legkiy_marshrut' => 96, 'step8_vizitnye_kartochki' => 92, 'step8_zashchita_ot_nepogody' => 75,
            'step8_uedinennost' => 45, 'step8_prostaya_logistika' => 94, 'step8_net_pozhelaniy' => 85,
        ],
    ];

    if (! isset($map[$template])) {
        throw new RuntimeException("Unknown template: {$template}");
    }

    return array_merge($base, $map[$template]);
}

/**
 * @return array<string, array{
 *   name_ru:string,
 *   template:string,
 *   tags:array<int,string>,
 *   scores?:array<string,int>,
 *   descriptions?:array<string,string>,
 *   intro?:string
 * }>
 */
function destinationDefinitions(): array
{
    return [
        // --- Hokkaido ---
        'Sapporo' => [
            'name_ru' => 'Саппоро',
            'template' => 'megacity',
            'tags' => ['хаб Хоккайдо', 'снежный фестиваль', 'рамен', 'ворота острова'],
            'scores' => [
                'step1_zimoy' => 94, 'step5_megapolisy' => 90, 'step6_lyzhi_zimniy' => 72,
                'step6_gastronomiya' => 94, 'step5_more_plyazhi_ostrova' => 25, 'step5_gory_lesa_doliny' => 45,
            ],
            'descriptions' => [
                'step1_zimoy' => 'Зима — визитка Саппоро: снежный фестиваль, иллюминации и удобная база к курортам острова.',
                'step5_megapolisy' => 'Крупнейший город Хоккайдо с метро, шопингом и ночной жизнью — урбан острова.',
                'step6_lyzhi_zimniy' => 'В городе склоны скромные, но логистика к Niseko/Furano делает Саппоро зимним хабом.',
                'step6_gastronomiya' => 'Суп-карри, рамен Мисо, морепродукты рынка — гастрономия одна из сильнейших на севере.',
            ],
        ],
        'Hakodate' => [
            'name_ru' => 'Хакодате',
            'template' => 'coastal_town',
            'tags' => ['ночной вид с горы', 'порт', 'утро у рынка', 'юг Хоккайдо'],
            'scores' => [
                'step5_more_plyazhi_ostrova' => 82, 'step6_gastronomiya' => 94, 'step5_megapolisy' => 35,
                'step6_lyzhi_zimniy' => 25, 'step8_vizitnye_kartochki' => 88, 'step2_dnya_3_4' => 94,
            ],
            'descriptions' => [
                'step6_gastronomiya' => 'Утренний рынок и свежие морепродукты — гастрономический must Хоккайдо.',
                'step5_more_plyazhi_ostrova' => 'Портовый город у пролива: море в кадре постоянно, но это не пляжный курорт Окинавы.',
                'step8_vizitnye_kartochki' => 'Ночной вид с Mount Hakodate — один из классических «открыточных» кадров Японии.',
            ],
        ],
        'Otaru' => [
            'name_ru' => 'Отару',
            'template' => 'coastal_town',
            'tags' => ['канал', 'склады Мэйдзи', 'день из Саппоро', 'сувениры'],
            'scores' => [
                'step2_dnya_1_2' => 96, 'step2_dney_5_7' => 45, 'step5_traditsionnye_goroda' => 72,
                'step6_gorodskaya_zhizn' => 40, 'step8_vizitnye_kartochki' => 80,
            ],
            'descriptions' => [
                'step2_dnya_1_2' => 'Классический day-trip или 1–2 ночи: канал, склады, стекло и морепродукты.',
                'step5_traditsionnye_goroda' => 'Атмосфера портового Мэйдзи, не храмовый Киото — но «старая Япония» ощущается.',
            ],
        ],
        'Furano' => [
            'name_ru' => 'Фурано',
            'template' => 'alpine_hiking',
            'tags' => ['лаванда', 'лето', 'семейные лыжи', 'долина'],
            'scores' => [
                'step1_letom' => 96, 'step1_zimoy' => 88, 'step6_lyzhi_zimniy' => 85,
                'step5_gory_lesa_doliny' => 94, 'step5_megapolisy' => 8, 'step6_gorodskaya_zhizn' => 15,
            ],
            'descriptions' => [
                'step1_letom' => 'Лето с лавандой и цветочными полями — главный «открыточный» сезон Фурано.',
                'step6_lyzhi_zimniy' => 'Мягкий семейный ski-профиль зимой; не powder-хайп Niseko, но уверенный снег.',
                'step5_gory_lesa_doliny' => 'Долины и холмы Хоккайдо — природа здесь главная причина приезда.',
            ],
        ],
        'Niseko' => [
            'name_ru' => 'Нисеко',
            'template' => 'ski_powder',
            'tags' => ['powder', 'международный курорт', 'апре-ски', 'онсэны рядом'],
            'scores' => [
                'step6_lyzhi_zimniy' => 99, 'step1_zimoy' => 99, 'step7_premialnyy' => 94,
                'step7_byudzhetnyy' => 28, 'step5_vulkany_onseny' => 72, 'step8_vizitnye_kartochki' => 88,
            ],
            'descriptions' => [
                'step6_lyzhi_zimniy' => 'Один из лучших powder-направлений Азии: снег и инфраструктура мирового уровня.',
                'step1_zimoy' => 'Зима — единственный «настоящий» сезон для большинства гостей Niseko.',
                'step7_byudzhetnyy' => 'Международный курорт с высокими ценами на жильё и еду — бюджетный формат сложен.',
                'step5_vulkany_onseny' => 'Онсэны и вулканический фон горы Йотэй дополняют ski-профиль.',
            ],
        ],
        'Asahikawa' => [
            'name_ru' => 'Асахикава',
            'template' => 'regional_hub',
            'tags' => ['ворота Дайсецудзана', 'зоопарк', 'сакэ', 'центр Хоккайдо'],
            'scores' => [
                'step5_gory_lesa_doliny' => 78, 'step6_hiking_priroda' => 82, 'step1_zimoy' => 88,
                'step6_lyzhi_zimniy' => 55, 'step5_megapolisy' => 55, 'step5_more_plyazhi_ostrova' => 15,
            ],
            'descriptions' => [
                'step5_gory_lesa_doliny' => 'Близость к Дайсецудзану делает Асахикаву природным хабом центра Хоккайдо.',
                'step6_hiking_priroda' => 'Отсюда удобно ехать в нацпарк и к горным тропам; сам город — база.',
            ],
        ],

        // --- Tohoku ---
        'Sendai' => [
            'name_ru' => 'Сендай',
            'template' => 'regional_hub',
            'tags' => ['хаб Тохоку', 'гютан', 'Мацусима рядом', 'зелёный город'],
            'scores' => [
                'step6_gastronomiya' => 90, 'step5_megapolisy' => 78, 'step8_prostaya_logistika' => 94,
                'step5_traditsionnye_goroda' => 48, 'step6_hramy_kultura' => 55,
            ],
            'descriptions' => [
                'step6_gastronomiya' => 'Гютан и плотная локальная сцена — Сендай силён едой и комфортом.',
                'step8_prostaya_logistika' => 'Крупный хаб синкансена: удобная точка входа в Тохоку без машины.',
            ],
        ],
        'Aomori' => [
            'name_ru' => 'Аомори',
            'template' => 'regional_hub',
            'tags' => ['нэбута', 'яблоки', 'север Хонсю', 'природа'],
            'scores' => [
                'step1_letom' => 90, 'step5_gory_lesa_doliny' => 75, 'step5_more_plyazhi_ostrova' => 55,
                'step5_megapolisy' => 45, 'step6_gorodskaya_zhizn' => 55, 'step6_hiking_priroda' => 78,
            ],
            'descriptions' => [
                'step1_letom' => 'Лето с фестивалем Нэбута — главный культурный пик Аомори.',
                'step5_gory_lesa_doliny' => 'Полуостров Симокита и природа севера — сильнее, чем урбан.',
            ],
        ],
        'Matsushima' => [
            'name_ru' => 'Мацусима',
            'template' => 'coastal_town',
            'tags' => ['три вида Японии', 'островки', 'лодки', 'день из Сендая'],
            'scores' => [
                'step5_more_plyazhi_ostrova' => 95, 'step5_ozera_vodopady_zelen' => 88, 'step2_dnya_1_2' => 96,
                'step8_vizitnye_kartochki' => 94, 'step6_gorodskaya_zhizn' => 20, 'step5_megapolisy' => 10,
            ],
            'descriptions' => [
                'step5_more_plyazhi_ostrova' => 'Бухта с сотнями островков — эталон «морского» пейзажа Японии без пляжного курорта.',
                'step8_vizitnye_kartochki' => 'Один из «трёх знаменитых видов» — must для открыточного списка.',
                'step2_dnya_1_2' => 'Оптимально как half-day/1–2 дня из Сендая.',
            ],
        ],
        'Aizu-Wakamatsu' => [
            'name_ru' => 'Айдзу-Вакамацу',
            'template' => 'traditional_city',
            'tags' => ['самураи', 'замок', 'сакэ', 'озеро Инавасиро'],
            'scores' => [
                'step5_traditsionnye_goroda' => 92, 'step5_ozera_vodopady_zelen' => 78, 'step5_megapolisy' => 12,
                'step6_hramy_kultura' => 88, 'step4_arendovannyy_avtomobil' => 80,
            ],
            'descriptions' => [
                'step5_traditsionnye_goroda' => 'Самурайская история и старый колорит Айдзу — сильная «традиционная» картинка Тохоку.',
                'step5_ozera_vodopady_zelen' => 'Озеро Инавасиро рядом усиливает природный фон города.',
            ],
        ],
        'Hirosaki' => [
            'name_ru' => 'Хиросаки',
            'template' => 'traditional_city',
            'tags' => ['сакура', 'замок', 'яблочный край', 'весна'],
            'scores' => [
                'step1_vesnoy' => 98, 'step5_traditsionnye_goroda' => 90, 'step5_ozera_vodopady_zelen' => 82,
                'step5_megapolisy' => 15, 'step2_dnya_3_4' => 90,
            ],
            'descriptions' => [
                'step1_vesnoy' => 'Один из лучших sakura-спотов севера: парк у замка — главный смысл весенней поездки.',
                'step5_traditsionnye_goroda' => 'Замок и провинциальный шарм — камерная традиционная Япония.',
            ],
        ],

        // --- Kanto ---
        'Tokyo' => [
            'name_ru' => 'Токио',
            'template' => 'megacity',
            'tags' => ['столица', 'хаб страны', 'шопинг', 'ночная жизнь'],
            'scores' => [
                'step5_megapolisy' => 100, 'step6_gorodskaya_zhizn' => 100, 'step6_gastronomiya' => 98,
                'step8_vizitnye_kartochki' => 98, 'step5_vulkany_onseny' => 15, 'step6_onseny' => 18,
                'step6_hramy_kultura' => 70, 'step7_premialnyy' => 92,
            ],
            'descriptions' => [
                'step5_megapolisy' => 'Эталон современной Японии: небоскрёбы, районы-контрасты и бесконечный городской ритм.',
                'step6_gorodskaya_zhizn' => 'Шопинг, ночь, pop-культура и развлечения — максимум среди всех направлений.',
                'step6_onseny' => 'Настоящие онсэны — day-trip в Хаконэ/Кусацу; в городе в основном сэнто и отельные спа.',
                'step8_vizitnye_kartochki' => 'Синдзюку, Сибуя, Асакуса — must первого визита в Японию.',
            ],
        ],
        'Yokohama' => [
            'name_ru' => 'Йокогама',
            'template' => 'megacity',
            'tags' => ['порт', 'Minato Mirai', 'Чайна-таун', 'рядом с Токио'],
            'scores' => [
                'step5_megapolisy' => 92, 'step5_more_plyazhi_ostrova' => 55, 'step2_dnya_1_2' => 94,
                'step8_vizitnye_kartochki' => 82, 'step6_gastronomiya' => 92,
            ],
            'descriptions' => [
                'step5_megapolisy' => 'Крупный порт и небоскрёбы Minato Mirai — современный городской вайб без токийской плотности.',
                'step2_dnya_1_2' => 'Идеален как день из Токио или короткая городская база.',
            ],
        ],
        'Kamakura' => [
            'name_ru' => 'Камакура',
            'template' => 'temple_heritage',
            'tags' => ['Большой Будда', 'храмы', 'океан', 'day-trip Токио'],
            'scores' => [
                'step5_more_plyazhi_ostrova' => 78, 'step5_traditsionnye_goroda' => 88, 'step2_dnya_1_2' => 97,
                'step6_hramy_kultura' => 95, 'step5_megapolisy' => 20,
            ],
            'descriptions' => [
                'step6_hramy_kultura' => 'Храмы и Дайбуцу — главный смысл Камакуры; культура сильнее ночного города.',
                'step5_more_plyazhi_ostrova' => 'Океан и пляж Юйгахама рядом с храмами — редкий mix Канто.',
                'step2_dnya_1_2' => 'Классический день из Токио; на неделю базы обычно не хватает.',
            ],
        ],
        'Hakone' => [
            'name_ru' => 'Хаконэ',
            'template' => 'onsen_resort',
            'tags' => ['онсэны', 'вид на Фудзи', 'weekend из Токио', 'озеро Аси'],
            'scores' => [
                'step5_vulkany_onseny' => 97, 'step5_ozera_vodopady_zelen' => 90, 'step6_onseny' => 98,
                'step8_vizitnye_kartochki' => 92, 'step3_para' => 97, 'step5_megapolisy' => 10,
            ],
            'descriptions' => [
                'step6_onseny' => 'Классика онсэн-уикенда из Токио: рёканы, открытые купели и курортный темп.',
                'step5_vulkany_onseny' => 'Вулканический ландшафт, адские долины и горячая вода — ДНК Хаконэ.',
                'step5_ozera_vodopady_zelen' => 'Озеро Аси и канатная дорога дают сильный «зелёный» и озёрный кадр.',
                'step3_para' => 'Один из лучших романтических сценариев Канто.',
            ],
        ],
        'Nikko' => [
            'name_ru' => 'Никко',
            'template' => 'temple_heritage',
            'tags' => ['Тосёгу', 'UNESCO', 'водопады', 'лес'],
            'scores' => [
                'step5_ozera_vodopady_zelen' => 94, 'step5_gory_lesa_doliny' => 88, 'step6_hramy_kultura' => 97,
                'step6_hiking_priroda' => 85, 'step8_vizitnye_kartochki' => 93, 'step1_osenyu' => 96,
            ],
            'descriptions' => [
                'step6_hramy_kultura' => 'Святилище Тосёгу — один из самых богато украшенных храмовых комплексов Японии.',
                'step5_ozera_vodopady_zelen' => 'Кэгон и озёрный район Чузендзи — природа не слабее храмов.',
                'step1_osenyu' => 'Осень с момдзи в горах Никко — один из лучших сезонов направления.',
            ],
        ],
        'Kusatsu' => [
            'name_ru' => 'Кусацу',
            'template' => 'onsen_resort',
            'tags' => ['топ-онсэн', 'юбатакэ', 'курорт', 'Гумма'],
            'scores' => [
                'step6_onseny' => 100, 'step5_vulkany_onseny' => 98, 'step5_megapolisy' => 8,
                'step8_zashchita_ot_nepogody' => 96, 'step7_premialnyy' => 82,
            ],
            'descriptions' => [
                'step6_onseny' => 'Один из «трёх великих онсэнов»: ради горячей воды сюда и едут.',
                'step5_vulkany_onseny' => 'Юбатакэ и сернистые источники — визуальный и термальный максимум.',
                'step8_zashchita_ot_nepogody' => 'Купание и курортная инфраструктура работают при дожде и холоде.',
            ],
        ],
        'Kawagoe' => [
            'name_ru' => 'Кавагоэ',
            'template' => 'daytrip_castle',
            'tags' => ['маленький Эдо', 'кура', 'сладкая улица', 'из Токио'],
            'scores' => [
                'step5_traditsionnye_goroda' => 94, 'step6_hramy_kultura' => 80, 'step2_dnya_1_2' => 98,
                'step6_gastronomiya' => 82, 'step5_megapolisy' => 25,
            ],
            'descriptions' => [
                'step5_traditsionnye_goroda' => 'Кура и улицы «маленького Эдо» — быстрый традиционный вайб без долгой дороги в Кансай.',
                'step2_dnya_1_2' => 'Идеальный day-trip из Токио; ночёвка обычно не обязательна.',
            ],
        ],

        // --- Hokuriku Shinetsu ---
        'Kanazawa' => [
            'name_ru' => 'Канадзава',
            'template' => 'traditional_city',
            'tags' => ['Кэнрокуэн', 'гейша-кварталы', 'крафт', 'кухня Хокурику'],
            'scores' => [
                'step5_traditsionnye_goroda' => 96, 'step6_hramy_kultura' => 92, 'step6_gastronomiya' => 95,
                'step5_megapolisy' => 30, 'step8_vizitnye_kartochki' => 90, 'step1_zimoy' => 72,
            ],
            'descriptions' => [
                'step5_traditsionnye_goroda' => 'Сад Кэнрокуэн, Хигаси-тяя и ремесленные кварталы — эталон традиционного города вне Киото.',
                'step6_gastronomiya' => 'Морепродукты Японского моря и рынок Омитё — гастрономия мирового уровня.',
                'step6_hramy_kultura' => 'Культура и крафт здесь не декорация, а ядро поездки.',
            ],
        ],
        'Takayama' => [
            'name_ru' => 'Такаяма',
            'template' => 'traditional_city',
            'tags' => ['старый город', 'Японские Альпы', 'Сиракава рядом', 'сакэ'],
            'scores' => [
                'step5_traditsionnye_goroda' => 95, 'step5_gory_lesa_doliny' => 88, 'step6_hiking_priroda' => 78,
                'step5_megapolisy' => 8, 'step4_arendovannyy_avtomobil' => 75, 'step8_uedinennost' => 62,
            ],
            'descriptions' => [
                'step5_traditsionnye_goroda' => 'Улицы Санмати — одна из самых цельных «старых» картинок в горах.',
                'step5_gory_lesa_doliny' => 'Расположение в Японских Альпах даёт сильный горный фон к традиционному центру.',
            ],
        ],
        'Nagano' => [
            'name_ru' => 'Нагано',
            'template' => 'alpine_hiking',
            'tags' => ['дзэнко-дзи', 'лыжный хаб', 'горы', 'ворота курортов'],
            'scores' => [
                'step6_lyzhi_zimniy' => 90, 'step1_zimoy' => 92, 'step6_hramy_kultura' => 82,
                'step5_gory_lesa_doliny' => 92, 'step5_megapolisy' => 35, 'step6_gorodskaya_zhizn' => 48,
            ],
            'descriptions' => [
                'step6_lyzhi_zimniy' => 'Хаб к Хакубе, Нодзаве и другим ski-зонам; зима — сильный мотив.',
                'step6_hramy_kultura' => 'Храм Дзэнко-дзи добавляет культурный якорь к горному профилю.',
                'step5_gory_lesa_doliny' => 'Горы префектуры — главная природная тема Нагано.',
            ],
        ],
        'Matsumoto' => [
            'name_ru' => 'Мацумото',
            'template' => 'alpine_hiking',
            'tags' => ['вороний замок', 'Камикоти', 'Альпы', 'хайкинг'],
            'scores' => [
                'step5_traditsionnye_goroda' => 78, 'step5_gory_lesa_doliny' => 96, 'step6_hiking_priroda' => 96,
                'step6_hramy_kultura' => 70, 'step1_letom' => 95, 'step5_megapolisy' => 15,
            ],
            'descriptions' => [
                'step6_hiking_priroda' => 'Ворота к Камикоти и Японским Альпам — хайкинг здесь ключевой продукт.',
                'step5_gory_lesa_doliny' => 'Альпийский доступ сильнее, чем городской вайб.',
                'step5_traditsionnye_goroda' => 'Чёрный замок — сильный heritage-кадр, но поездку часто строят вокруг гор.',
            ],
        ],
        'Karuizawa' => [
            'name_ru' => 'Каруидзава',
            'template' => 'alpine_hiking',
            'tags' => ['курорт у Асамы', 'прогулки', 'outlet', 'комфорт'],
            'scores' => [
                'step7_povyshennyy_komfort' => 92, 'step7_premialnyy' => 85, 'step5_vulkany_onseny' => 70,
                'step6_gorodskaya_zhizn' => 55, 'step6_lyzhi_zimniy' => 55, 'step8_legkiy_marshrut' => 90,
                'step3_semya_do_6' => 88,
            ],
            'descriptions' => [
                'step7_povyshennyy_komfort' => 'Курортный комфорт, прогулочные тропы и шопинг — более «мягкий» отдых у природы.',
                'step8_legkiy_marshrut' => 'Понятные прогулки и инфраструктура удобны новичкам и семьям.',
                'step5_vulkany_onseny' => 'Фон вулкана Асама и онсэн-опции рядом усиливают термально-природный профиль.',
            ],
        ],
        'Shirakawa-go' => [
            'name_ru' => 'Сиракава-го',
            'template' => 'daytrip_castle',
            'tags' => ['гассё-дзукури', 'UNESCO', 'фото', 'из Такаямы'],
            'scores' => [
                'step5_traditsionnye_goroda' => 97, 'step5_gory_lesa_doliny' => 90, 'step2_dnya_1_2' => 98,
                'step8_vizitnye_kartochki' => 95, 'step1_zimoy' => 92, 'step6_gorodskaya_zhizn' => 5,
                'step8_uedinennost' => 55, 'step4_arendovannyy_avtomobil' => 80,
            ],
            'descriptions' => [
                'step5_traditsionnye_goroda' => 'Деревни гассё-дзукури — уникальная «старинная» архитектура мирового уровня.',
                'step2_dnya_1_2' => 'Чаще day-trip из Такаямы; долгая база обычно там, а не в самой деревне.',
                'step1_zimoy' => 'Зимние огоньки и снег на крышах — один из самых фотогеничных сезонов.',
            ],
        ],
        'Niigata' => [
            'name_ru' => 'Ниигата',
            'template' => 'regional_hub',
            'tags' => ['сакэ', 'рис', 'Японское море', 'снег'],
            'scores' => [
                'step6_gastronomiya' => 92, 'step1_zimoy' => 88, 'step6_lyzhi_zimniy' => 75,
                'step5_more_plyazhi_ostrova' => 70, 'step5_megapolisy' => 60,
            ],
            'descriptions' => [
                'step6_gastronomiya' => 'Сакэ и рис — гастрономический бренд префектуры.',
                'step6_lyzhi_zimniy' => 'Доступ к снежным курортам побережья Японского моря сильный зимой.',
            ],
        ],

        // --- Tokai ---
        'Nagoya' => [
            'name_ru' => 'Нагоя',
            'template' => 'regional_hub',
            'tags' => ['хаб Токайдо', 'хицумабуси', 'замок', 'между Токио и Осакой'],
            'scores' => [
                'step5_megapolisy' => 85, 'step8_prostaya_logistika' => 96, 'step6_gorodskaya_zhizn' => 82,
                'step6_gastronomiya' => 90, 'step8_vizitnye_kartochki' => 68,
            ],
            'descriptions' => [
                'step8_prostaya_logistika' => 'Один из лучших транспортных узлов страны — удобно как база или пересадка.',
                'step6_gastronomiya' => 'Хицумабуси, мисо-кацу и плотная локальная сцена.',
            ],
        ],
        'Ise' => [
            'name_ru' => 'Исэ',
            'template' => 'temple_heritage',
            'tags' => ['Великое святилище', 'паломничество', 'Охараи-мати', 'традиция'],
            'scores' => [
                'step6_hramy_kultura' => 99, 'step5_traditsionnye_goroda' => 90, 'step5_more_plyazhi_ostrova' => 55,
                'step8_vizitnye_kartochki' => 90, 'step6_gorodskaya_zhizn' => 20,
            ],
            'descriptions' => [
                'step6_hramy_kultura' => 'Исэ-дзингу — духовный центр синто; культурный вес максимальный.',
                'step5_traditsionnye_goroda' => 'Охараи-мати и паломническая атмосфера — традиция без мегаполисного шума.',
            ],
        ],
        'Shizuoka' => [
            'name_ru' => 'Сидзуока',
            'template' => 'coastal_town',
            'tags' => ['чай', 'вид на Фудзи', 'Идзу рядом', 'Токайдо'],
            'scores' => [
                'step5_gory_lesa_doliny' => 70, 'step5_more_plyazhi_ostrova' => 75, 'step6_hiking_priroda' => 72,
                'step5_ozera_vodopady_zelen' => 70, 'step5_megapolisy' => 40,
            ],
            'descriptions' => [
                'step5_more_plyazhi_ostrova' => 'Побережье и доступ к Идзу дают морской акцент без окинавских кораллов.',
                'step5_gory_lesa_doliny' => 'Виды на Фудзи и холмы чайных плантаций — природа рядом с городом.',
            ],
        ],
        'Hamamatsu' => [
            'name_ru' => 'Хамамацу',
            'template' => 'regional_hub',
            'tags' => ['озеро Хамана', 'унаги', 'Токайдо', 'транзит'],
            'scores' => [
                'step5_ozera_vodopady_zelen' => 78, 'step6_gastronomiya' => 88, 'step5_megapolisy' => 55,
                'step8_prostaya_logistika' => 90, 'step8_vizitnye_kartochki' => 55,
            ],
            'descriptions' => [
                'step5_ozera_vodopady_zelen' => 'Озеро Хамана — главный природный якорь города.',
                'step6_gastronomiya' => 'Унаги и локальная кухня сильнее «открыточного» туризма.',
            ],
        ],

        // --- Kansai ---
        'Kyoto' => [
            'name_ru' => 'Киото',
            'template' => 'traditional_city',
            'tags' => ['храмы', 'гейши', 'сады', 'традиция №1'],
            'scores' => [
                'step5_traditsionnye_goroda' => 100, 'step6_hramy_kultura' => 100, 'step1_vesnoy' => 98,
                'step1_osenyu' => 98, 'step8_vizitnye_kartochki' => 100, 'step5_megapolisy' => 35,
                'step6_gorodskaya_zhizn' => 60, 'step7_premialnyy' => 88, 'step8_uedinennost' => 28,
            ],
            'descriptions' => [
                'step5_traditsionnye_goroda' => 'Абсолютный эталон традиционной Японии: храмы, машины, кварталы гейш.',
                'step6_hramy_kultura' => 'Плотность святынь и садов не имеет аналогов — культура здесь главный смысл поездки.',
                'step8_vizitnye_kartochki' => 'Фусими Инари, Кинкаку-дзи, Гион — must любого первого маршрута.',
                'step8_uedinennost' => 'В высокий сезон очень людно; тишина — рано утром или в менее раскрученных районах.',
                'step1_vesnoy' => 'Сакура в Киото — один из самых желанных сезонов в мире.',
            ],
        ],
        'Osaka' => [
            'name_ru' => 'Осака',
            'template' => 'megacity',
            'tags' => ['кухня', 'Дотонбори', 'ночь', 'хаб Кансая'],
            'scores' => [
                'step6_gastronomiya' => 100, 'step6_gorodskaya_zhizn' => 96, 'step5_megapolisy' => 95,
                'step6_hramy_kultura' => 45, 'step8_vizitnye_kartochki' => 90, 'step7_byudzhetnyy' => 58,
            ],
            'descriptions' => [
                'step6_gastronomiya' => '«Кухня нации»: такояки, окономияки, кусикацу — гастрономия №1 мотивация.',
                'step5_megapolisy' => 'Яркий мегаполис с более расслабленным характером, чем Токио.',
                'step6_hramy_kultura' => 'Храмы рядом в Киото/Наре; сама Осака скорее про еду и город.',
            ],
        ],
        'Nara' => [
            'name_ru' => 'Нара',
            'template' => 'temple_heritage',
            'tags' => ['Тодай-дзи', 'олени', 'первая столица', 'день из Киото'],
            'scores' => [
                'step6_hramy_kultura' => 98, 'step5_traditsionnye_goroda' => 92, 'step5_ozera_vodopady_zelen' => 85,
                'step2_dnya_1_2' => 96, 'step3_semya_ot_7' => 92, 'step8_vizitnye_kartochki' => 94,
            ],
            'descriptions' => [
                'step6_hramy_kultura' => 'Великий Будда Тодай-дзи и парк — must культурной программы Кансая.',
                'step5_ozera_vodopady_zelen' => 'Парк с оленями и зелёные аллеи дают мягкий природный фон к храмам.',
                'step2_dnya_1_2' => 'Чаще день из Киото/Осаки; на длинную базу обычно выбирают Киото.',
            ],
        ],
        'Kobe' => [
            'name_ru' => 'Кобе',
            'template' => 'regional_hub',
            'tags' => ['говядина', 'порт', 'Рокко', 'европейский колорит'],
            'scores' => [
                'step6_gastronomiya' => 96, 'step5_megapolisy' => 75, 'step5_gory_lesa_doliny' => 70,
                'step5_more_plyazhi_ostrova' => 55, 'step7_premialnyy' => 85, 'step6_hiking_priroda' => 70,
            ],
            'descriptions' => [
                'step6_gastronomiya' => 'Кобе-говядина и портовая кухня — гастрономический якорь города.',
                'step5_gory_lesa_doliny' => 'Горы Рокко рядом дают виды и лёгкий хайкинг без отъезда далеко.',
            ],
        ],
        'Himeji' => [
            'name_ru' => 'Химэдзи',
            'template' => 'daytrip_castle',
            'tags' => ['белый замок', 'UNESCO', 'день из Кансая'],
            'scores' => [
                'step5_traditsionnye_goroda' => 96, 'step6_hramy_kultura' => 90, 'step8_vizitnye_kartochki' => 96,
                'step2_dnya_1_2' => 99,
            ],
            'descriptions' => [
                'step5_traditsionnye_goroda' => 'Замок-цапля — икона японской замковой архитектуры.',
                'step2_dnya_1_2' => 'Оптимален как день из Осаки/Киото; на многодневную базу города маловато.',
                'step8_vizitnye_kartochki' => 'Один из самых узнаваемых кадров Японии.',
            ],
        ],
        'Uji' => [
            'name_ru' => 'Удзи',
            'template' => 'temple_heritage',
            'tags' => ['матча', 'Бёдо-ин', 'между Киото и Нарой'],
            'scores' => [
                'step6_gastronomiya' => 92, 'step6_hramy_kultura' => 94, 'step2_dnya_1_2' => 96,
                'step5_traditsionnye_goroda' => 88, 'step8_uedinennost' => 60,
            ],
            'descriptions' => [
                'step6_gastronomiya' => 'Чайный город: матча и сладости — гастрономический must рядом с Киото.',
                'step6_hramy_kultura' => 'Бёдо-ин на купюре — компактный, но мощный культурный якорь.',
            ],
        ],
        'Koyasan' => [
            'name_ru' => 'Коя-сан',
            'template' => 'temple_heritage',
            'tags' => ['Сингон', 'сёкубо', 'кладбище Окуно-ин', 'гора'],
            'scores' => [
                'step6_hramy_kultura' => 99, 'step5_gory_lesa_doliny' => 90, 'step8_uedinennost' => 85,
                'step6_gorodskaya_zhizn' => 5, 'step5_megapolisy' => 5, 'step3_para' => 90,
                'step2_dnya_1_2' => 70, 'step2_dnya_3_4' => 92, 'step7_premialnyy' => 70,
            ],
            'descriptions' => [
                'step6_hramy_kultura' => 'Монастырская гора с ночёвкой в сёкубо — уникальный духовный опыт.',
                'step8_uedinennost' => 'Тишина леса и ритуалов — антипод мегаполиса.',
                'step5_gory_lesa_doliny' => 'Горный лес и тропы Окуно-ин — природа неотделима от храмов.',
            ],
        ],

        // --- Chugoku ---
        'Hiroshima' => [
            'name_ru' => 'Хиросима',
            'template' => 'regional_hub',
            'tags' => ['город мира', 'окономаки', 'хаб к Миядзиме', 'история'],
            'scores' => [
                'step6_hramy_kultura' => 78, 'step6_gastronomiya' => 90, 'step5_megapolisy' => 75,
                'step8_vizitnye_kartochki' => 88, 'step8_prostaya_logistika' => 94,
            ],
            'descriptions' => [
                'step8_vizitnye_kartochki' => 'Мемориальный парк и логика к Миядзиме делают Хиросиму важным пунктом маршрута.',
                'step6_gastronomiya' => 'Окономияки Хиросимы — отдельный гастробренд.',
            ],
        ],
        'Miyajima' => [
            'name_ru' => 'Миядзима',
            'template' => 'temple_heritage',
            'tags' => ['торii на воде', 'Ицукусима', 'остров', 'символ Японии'],
            'scores' => [
                'step5_more_plyazhi_ostrova' => 88, 'step6_hramy_kultura' => 97, 'step8_vizitnye_kartochki' => 99,
                'step5_ozera_vodopady_zelen' => 85, 'step2_dnya_1_2' => 94, 'step5_megapolisy' => 8,
                'step6_hiking_priroda' => 80,
            ],
            'descriptions' => [
                'step8_vizitnye_kartochki' => 'Плавающие торii — один из самых узнаваемых символов Японии.',
                'step6_hramy_kultura' => 'Святилище Ицукусима — UNESCO и духовный центр острова.',
                'step5_more_plyazhi_ostrova' => 'Остров во Внутреннем море: вода и паром — часть опыта.',
            ],
        ],
        'Okayama' => [
            'name_ru' => 'Окаяма',
            'template' => 'regional_hub',
            'tags' => ['Коракуэн', 'ворота Курасики', 'комфорт', 'транзит'],
            'scores' => [
                'step5_ozera_vodopady_zelen' => 82, 'step5_traditsionnye_goroda' => 65, 'step8_prostaya_logistika' => 92,
                'step8_vizitnye_kartochki' => 70,
            ],
            'descriptions' => [
                'step5_ozera_vodopady_zelen' => 'Сад Коракуэн — один из «трёх великих садов», сильный зелёный акцент.',
                'step8_prostaya_logistika' => 'Удобный хаб Тюгоку и база к Курасики.',
            ],
        ],
        'Kurashiki' => [
            'name_ru' => 'Курасики',
            'template' => 'traditional_city',
            'tags' => ['канал', 'белые кура', 'музейный квартал', 'камерность'],
            'scores' => [
                'step5_traditsionnye_goroda' => 94, 'step2_dnya_1_2' => 94, 'step2_dney_5_7' => 45,
                'step5_megapolisy' => 12, 'step8_uedinennost' => 60, 'step6_gorodskaya_zhizn' => 30,
            ],
            'descriptions' => [
                'step5_traditsionnye_goroda' => 'Канал и склады кура — камерная историческая картинка без толп Киото.',
                'step2_dnya_1_2' => 'Чаще 1 день из Окаямы; на неделю базы обычно маловато.',
            ],
        ],
        'Tottori' => [
            'name_ru' => 'Тоттори',
            'template' => 'coastal_town',
            'tags' => ['дюны', 'Японское море', 'необычный пейзаж'],
            'scores' => [
                'step5_more_plyazhi_ostrova' => 92, 'step5_gory_lesa_doliny' => 55, 'step8_vizitnye_kartochki' => 78,
                'step5_traditsionnye_goroda' => 40, 'step6_hiking_priroda' => 75, 'step8_uedinennost' => 70,
            ],
            'descriptions' => [
                'step5_more_plyazhi_ostrova' => 'Песчаные дюны у моря — уникальный для Японии «пустынный» береговой кадр.',
                'step8_uedinennost' => 'Заметно спокойнее золотого маршрута Кансая.',
            ],
        ],
        'Onomichi' => [
            'name_ru' => 'Ономити',
            'template' => 'alpine_hiking',
            'tags' => ['Симанами', 'велосипед', 'храмы на склонах', 'винтаж'],
            'scores' => [
                'step5_more_plyazhi_ostrova' => 80, 'step5_gory_lesa_doliny' => 70, 'step6_hiking_priroda' => 92,
                'step5_traditsionnye_goroda' => 72, 'step1_letom' => 88, 'step5_megapolisy' => 15,
                'step6_lyzhi_zimniy' => 5,
            ],
            'descriptions' => [
                'step6_hiking_priroda' => 'Старт Симанами Кайдо: велосипед и тропы между островами — главный актив.',
                'step5_more_plyazhi_ostrova' => 'Внутреннее море и мосты — морской пейзаж без пляжного курорта.',
            ],
        ],

        // --- Shikoku ---
        'Takamatsu' => [
            'name_ru' => 'Такамацу',
            'template' => 'regional_hub',
            'tags' => ['хаб Сикоку', 'Рицурин', 'удон', 'Наосима рядом'],
            'scores' => [
                'step5_ozera_vodopady_zelen' => 85, 'step6_gastronomiya' => 90, 'step5_more_plyazhi_ostrova' => 70,
                'step8_prostaya_logistika' => 90, 'step5_megapolisy' => 50,
            ],
            'descriptions' => [
                'step5_ozera_vodopady_zelen' => 'Сад Рицурин — зелёная визитка города.',
                'step6_gastronomiya' => 'Удон Санки — обязательный гастрономический ритуал Сикоку.',
            ],
        ],
        'Matsuyama' => [
            'name_ru' => 'Мацуяма',
            'template' => 'onsen_resort',
            'tags' => ['Дого онсэн', 'замок', 'Сикоку', 'история'],
            'scores' => [
                'step6_onseny' => 95, 'step5_vulkany_onseny' => 88, 'step5_traditsionnye_goroda' => 80,
                'step6_hramy_kultura' => 75, 'step5_megapolisy' => 35,
            ],
            'descriptions' => [
                'step6_onseny' => 'Дого — один из старейших онсэнов Японии; термальный опыт здесь ключевой.',
                'step5_traditsionnye_goroda' => 'Замок и исторический курортный квартал усиливают традиционный фон.',
            ],
        ],
        'Tokushima' => [
            'name_ru' => 'Токусима',
            'template' => 'regional_hub',
            'tags' => ['Ава-одори', 'природа', 'восток Сикоку'],
            'scores' => [
                'step1_letom' => 92, 'step5_gory_lesa_doliny' => 80, 'step6_hiking_priroda' => 82,
                'step5_megapolisy' => 40, 'step6_gorodskaya_zhizn' => 50,
            ],
            'descriptions' => [
                'step1_letom' => 'Лето с Ава-одори — главный культурный пик Токусимы.',
                'step5_gory_lesa_doliny' => 'Ущелья и природа восточного Сикоку сильнее урбана.',
            ],
        ],
        'Kochi' => [
            'name_ru' => 'Коти',
            'template' => 'coastal_town',
            'tags' => ['Хиромарти', 'замок', 'тихоокеанский берег', 'кацуо'],
            'scores' => [
                'step6_gastronomiya' => 92, 'step5_more_plyazhi_ostrova' => 85, 'step5_traditsionnye_goroda' => 70,
                'step8_uedinennost' => 62, 'step5_megapolisy' => 30,
            ],
            'descriptions' => [
                'step6_gastronomiya' => 'Рынок Хиромарти и кацуо — гастрономический характер Коти.',
                'step5_more_plyazhi_ostrova' => 'Тихоокеанское побережье даёт сильный морской профиль.',
            ],
        ],
        'Naoshima' => [
            'name_ru' => 'Наосима',
            'template' => 'art_island',
            'tags' => ['современное искусство', 'Бенэссе', 'Сэто', 'камерность'],
            'scores' => [
                'step5_more_plyazhi_ostrova' => 90, 'step8_vizitnye_kartochki' => 90, 'step6_hramy_kultura' => 70,
                'step8_uedinennost' => 78, 'step3_semya_do_6' => 45,
            ],
            'descriptions' => [
                'step5_more_plyazhi_ostrova' => 'Остров во Внутреннем море: вода и паромы — часть арт-маршрута.',
                'step8_vizitnye_kartochki' => 'Тыквы Кусамы и музеи Бенэссе — современная визитка Японии.',
                'step8_uedinennost' => 'Камерный островной темп, особенно вне пиковых уик-эндов.',
            ],
        ],

        // --- Kyushu ---
        'Fukuoka' => [
            'name_ru' => 'Фукуока',
            'template' => 'megacity',
            'tags' => ['хаб Кюсю', 'ятай', 'рамен', 'короткий перелёт'],
            'scores' => [
                'step5_megapolisy' => 90, 'step6_gastronomiya' => 96, 'step6_gorodskaya_zhizn' => 90,
                'step5_more_plyazhi_ostrova' => 55, 'step8_prostaya_logistika' => 94, 'step7_byudzhetnyy' => 55,
            ],
            'descriptions' => [
                'step6_gastronomiya' => 'Ятай и рамен Хаката — один из лучших food-городов Японии.',
                'step5_megapolisy' => 'Живой хаб Кюсю с комфортным урбаном и доступом по острову.',
            ],
        ],
        'Nagasaki' => [
            'name_ru' => 'Нагасаки',
            'template' => 'regional_hub',
            'tags' => ['порт', 'многослойная история', 'холмы', 'кухня'],
            'scores' => [
                'step6_hramy_kultura' => 85, 'step5_traditsionnye_goroda' => 70, 'step5_more_plyazhi_ostrova' => 65,
                'step6_gastronomiya' => 90, 'step5_megapolisy' => 60, 'step8_vizitnye_kartochki' => 85,
            ],
            'descriptions' => [
                'step6_hramy_kultura' => 'История порта, Дедзима и мемориалы дают плотную культурную программу.',
                'step6_gastronomiya' => 'Чанпон и портовая кухня — сильный локальный вкус.',
            ],
        ],
        'Kumamoto' => [
            'name_ru' => 'Кумамото',
            'template' => 'volcano_south',
            'tags' => ['замок', 'ворота Асо', 'вулкан', 'локальная еда'],
            'scores' => [
                'step5_vulkany_onseny' => 92, 'step5_traditsionnye_goroda' => 75, 'step6_hiking_priroda' => 88,
                'step5_gory_lesa_doliny' => 85, 'step6_hramy_kultura' => 60,
            ],
            'descriptions' => [
                'step5_vulkany_onseny' => 'Ворота к кальдере Асо — вулканический пейзаж Кюсю рядом.',
                'step6_hiking_priroda' => 'Тропы и панорамы Асо — главный природный выезд из города.',
            ],
        ],
        'Beppu' => [
            'name_ru' => 'Беппу',
            'template' => 'onsen_resort',
            'tags' => ['столица онсэнов', 'адские источники', 'Кюсю'],
            'scores' => [
                'step6_onseny' => 100, 'step5_vulkany_onseny' => 99, 'step8_zashchita_ot_nepogody' => 96,
                'step5_megapolisy' => 20, 'step6_gorodskaya_zhizn' => 35,
            ],
            'descriptions' => [
                'step6_onseny' => 'Тысячи источников: Беппу — синоним онсэн-туризма Кюсю.',
                'step5_vulkany_onseny' => '«Адские» котлы и пар — визуальный максимум термальной Японии.',
            ],
        ],
        'Kagoshima' => [
            'name_ru' => 'Кагосима',
            'template' => 'volcano_south',
            'tags' => ['Сакурадзима', 'юг Кюсю', 'море', 'еда'],
            'scores' => [
                'step5_vulkany_onseny' => 98, 'step5_more_plyazhi_ostrova' => 75, 'step6_gastronomiya' => 90,
                'step5_megapolisy' => 45, 'step1_letom' => 85,
            ],
            'descriptions' => [
                'step5_vulkany_onseny' => 'Вид на Сакурадзиму — один из самых сильных вулканических кадров Японии.',
                'step5_more_plyazhi_ostrova' => 'Южный характер и море усиливают профиль, но это не Окинава-пляж.',
            ],
        ],
        'Yufuin' => [
            'name_ru' => 'Юфуин',
            'template' => 'onsen_resort',
            'tags' => ['стильный онсэн', 'долина', 'камерный люкс', 'Юфу-дакэ'],
            'scores' => [
                'step6_onseny' => 97, 'step7_premialnyy' => 92, 'step8_uedinennost' => 78,
                'step5_gory_lesa_doliny' => 88, 'step5_ozera_vodopady_zelen' => 85, 'step6_gorodskaya_zhizn' => 25,
            ],
            'descriptions' => [
                'step6_onseny' => 'Стильный онсэн-курорт: рёканы и прогулочная долина у Юфу-дакэ.',
                'step7_premialnyy' => 'Камерный люкс и дизайн-отели — естественная ценовая полка Юфуина.',
                'step8_uedinennost' => 'Спокойнее и «тише» массового Беппу.',
            ],
        ],

        // --- Okinawa ---
        'Naha' => [
            'name_ru' => 'Наха',
            'template' => 'island_beach',
            'tags' => ['столица Окинавы', 'хаб островов', 'Кокусай-дори', 'море'],
            'scores' => [
                'step5_megapolisy' => 55, 'step6_gorodskaya_zhizn' => 70, 'step5_more_plyazhi_ostrova' => 92,
                'step6_hramy_kultura' => 55, 'step8_prostaya_logistika' => 88, 'step1_zimoy' => 70,
            ],
            'descriptions' => [
                'step5_more_plyazhi_ostrova' => 'Старт архипелага: море и субтропический ритм сразу у столицы.',
                'step6_gorodskaya_zhizn' => 'Кокусай-дори и городская база — урбан Окинавы, но не Токио.',
                'step1_zimoy' => 'Зима мягкая и без снега: для тепла, не для лыж.',
            ],
        ],
        'Ishigaki' => [
            'name_ru' => 'Исигаки',
            'template' => 'island_beach',
            'tags' => ['Яэяма', 'снорклинг', 'джунгли', 'острова'],
            'scores' => [
                'step5_more_plyazhi_ostrova' => 100, 'step6_hiking_priroda' => 88, 'step8_uedinennost' => 72,
                'step5_traditsionnye_goroda' => 30, 'step6_lyzhi_zimniy' => 0, 'step8_vizitnye_kartochki' => 82,
            ],
            'descriptions' => [
                'step5_more_plyazhi_ostrova' => 'База к Яэяме: кораллы, острова и бирюзовая вода — максимум морского сценария.',
                'step6_hiking_priroda' => 'Джунгли, мысы и выезды на соседние острова — природа главнее города.',
                'step6_lyzhi_zimniy' => 'Снега нет совсем — зимний спорт здесь невозможен.',
            ],
        ],
        'Miyako' => [
            'name_ru' => 'Мияко',
            'template' => 'island_beach',
            'tags' => ['бирюзовая вода', 'пляжи', 'релакс'],
            'scores' => [
                'step5_more_plyazhi_ostrova' => 100, 'step6_gorodskaya_zhizn' => 25, 'step8_uedinennost' => 75,
                'step3_para' => 96, 'step6_hramy_kultura' => 25, 'step1_letom' => 97,
            ],
            'descriptions' => [
                'step5_more_plyazhi_ostrova' => 'Эталон пляжного отдыха Окинавы: цвет воды — главная причина ехать.',
                'step3_para' => 'Релакс и пляжи делают Мияко сильным выбором для пары.',
                'step6_gorodskaya_zhizn' => 'Городской жизни мало — это островной beach-сценарий.',
            ],
        ],
        'Nago' => [
            'name_ru' => 'Наго',
            'template' => 'island_beach',
            'tags' => ['север Окинавы', 'аквариум', 'семья', 'бухты'],
            'scores' => [
                'step3_semya_do_6' => 94, 'step3_semya_ot_7' => 95, 'step5_more_plyazhi_ostrova' => 95,
                'step6_gorodskaya_zhizn' => 35, 'step8_legkiy_marshrut' => 90,
            ],
            'descriptions' => [
                'step3_semya_do_6' => 'Аквариум Тюрауми и спокойные бухты — один из лучших семейных сценариев Окинавы.',
                'step5_more_plyazhi_ostrova' => 'Север острова: море и природа сильнее ночного города Нахи.',
            ],
        ],
    ];
}
