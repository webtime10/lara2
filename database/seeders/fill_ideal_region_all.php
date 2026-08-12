<?php
/**
 * Fill Ideal Region scores + RU descriptions for all active Swiss categories
 * except Lucerne (id=7), which is already filled as the quality sample.
 *
 * Usage:
 *   php database/seeders/fill_ideal_region_all.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Category;
use App\Models\CategoryDescription;

const SKIP_CATEGORY_ID = 7;
const MANUFACTURER_ID = 1;

$scoreFields = scoreFieldKeys();
$optionTitles = (array) config('ideal_region_category_fields.option_titles', []);

$regions = regionDefinitions();

$filled = 0;
$skipped = 0;
$errors = 0;

$categories = Category::query()
    ->where('manufacturer_id', MANUFACTURER_ID)
    ->where('status', true)
    ->with('descriptions')
    ->orderBy('id')
    ->get();

foreach ($categories as $category) {
    $id = (int) $category->id;

    if ($id === SKIP_CATEGORY_ID) {
        echo "[skip] id={$id} Lucerne (already filled)\n";
        $skipped++;
        continue;
    }

    if (! isset($regions[$id])) {
        echo "[error] id={$id} — no region definition\n";
        $errors++;
        continue;
    }

    /** @var CategoryDescription|null $desc */
    $desc = $category->descriptions->first();
    if (! $desc) {
        echo "[error] id={$id} — no CategoryDescription row\n";
        $errors++;
        continue;
    }

    $def = $regions[$id];
    $nameEn = (string) $def['name'];
    $nameRu = (string) $def['name_ru'];
    $tags = (array) ($def['tags'] ?? []);

    $scores = array_merge(
        templateScores((string) $def['template']),
        (array) ($def['scores'] ?? [])
    );

    // Ensure all score keys exist and are string integers 0–100.
    foreach ($scoreFields as $field) {
        if (! array_key_exists($field, $scores)) {
            echo "[error] id={$id} missing score field {$field}\n";
            $errors++;
            continue 2;
        }
        $n = (int) $scores[$field];
        $n = max(0, min(100, $n));
        $scores[$field] = (string) $n;
    }

    $descOverrides = (array) ($def['descriptions'] ?? []);
    $payload = ['description' => (string) $def['intro']];

    foreach ($scoreFields as $field) {
        $payload[$field] = $scores[$field];
        $descKey = $field . '_description';
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
        echo "[ok] id={$id} {$nameEn}\n";
        $filled++;
    } catch (Throwable $e) {
        echo "[error] id={$id} {$nameEn}: {$e->getMessage()}\n";
        $errors++;
    }
}

echo "\nDone. filled={$filled} skipped={$skipped} errors={$errors}\n";
exit($errors > 0 ? 1 : 0);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function scoreFieldKeys(): array
{
    $fields = (array) config('ideal_region_category_fields.fields', []);
    return array_values(array_filter(
        $fields,
        static fn ($f) => is_string($f) && str_starts_with($f, 'step') && ! str_ends_with($f, '_description')
    ));
}

/**
 * Band label for fallback texts.
 */
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

/**
 * Theme hint by field key (used when region has no custom override).
 */
function fieldThemeHint(string $field): string
{
    static $hints = [
        'step1_vesnoy' => 'весенний сезон (мягкая погода, начало сезона подъёмников и озёрных рейсов)',
        'step1_letom' => 'летний сезон (хайкинг, озёра, полная работа канатных дорог)',
        'step1_osenyu' => 'осень (золотые леса, спокойнее туризм, вино/урожай где уместно)',
        'step1_zimoy' => 'зима (лыжи, атмосфера курорта, музеи/город при слабом снеге)',
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
        'step4_obshchestvennyy_transport' => 'общественный транспорт (поезда, автобусы, подъёмники)',
        'step4_arendovannyy_avtomobil' => 'арендованный автомобиль',
        'step4_sochetanie' => 'сочетание авто и ОТ',
        'step5_vysokogornye_alpy' => 'высокогорные Альпы и ледники',
        'step5_ozera_vodopady' => 'озёра и водопады',
        'step5_sredizemnomorskiy_vayb' => 'средиземноморский / южный вайб',
        'step5_alpiyskie_luga' => 'альпийские луга и деревни',
        'step5_istoricheskie_goroda' => 'исторический городской облик',
        'step5_vinodelcheskie_terrasy' => 'винодельческие террасы и фермы',
        'step6_peshie_progulki' => 'пешие прогулки и хайкинг',
        'step6_gornye_lyzhi' => 'горные лыжи и зимний спорт',
        'step6_panoramnye_poezda' => 'панорамные поезда и подъёмники',
        'step6_ekskursii_muzei' => 'музеи, галереи и замки',
        'step6_gastronomiya' => 'гастрономия',
        'step6_spa_ozdorovlenie' => 'СПА и оздоровление',
        'step7_byudzhetnyy' => 'бюджетный ценовой формат',
        'step7_standartnyy' => 'стандартный ценовой формат',
        'step7_povyshennyy_komfort' => 'повышенный комфорт',
        'step7_premialnyy' => 'премиальный формат',
        'step8_legkiy_marshrut' => 'лёгкий маршрут без сложных подходов',
        'step8_vizitnye_kartochki' => 'главные визитные карточки Швейцарии',
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
    $tagLine = $tags !== [] ? (' Контекст региона: ' . implode(', ', array_slice($tags, 0, 4)) . '.') : '';

    if ($score >= 75) {
        return "В регионе {$nameRu} ({$nameEn}) критерий «{$optionTitle}» оценивается как {$band} ({$score}/100): {$theme} здесь действительно сильная сторона.{$tagLine}";
    }
    if ($score >= 45) {
        return "Для {$nameRu} «{$optionTitle}» — скорее средний результат ({$score}/100): {$theme} присутствует, но не является главной визиткой региона.{$tagLine}";
    }

    return "В {$nameRu} критерий «{$optionTitle}» оценивается низко ({$score}/100): {$theme} здесь слабо выражена или требует выезда в соседние зоны.{$tagLine}";
}

/**
 * Soft defaults — templates override these heavily.
 *
 * @return array<string,int>
 */
function neutralBase(): array
{
    return [
        'step1_vesnoy' => 78,
        'step1_letom' => 88,
        'step1_osenyu' => 80,
        'step1_zimoy' => 70,
        'step2_dnya_1_2' => 78,
        'step2_dnya_3_4' => 90,
        'step2_dney_5_7' => 88,
        'step2_dney_8_10' => 70,
        'step2_bolee_10_dney' => 55,
        'step3_solo' => 82,
        'step3_para' => 88,
        'step3_kompaniya_druzei' => 82,
        'step3_semya_do_6' => 72,
        'step3_semya_ot_7' => 82,
        'step4_obshchestvennyy_transport' => 80,
        'step4_arendovannyy_avtomobil' => 75,
        'step4_sochetanie' => 85,
        'step5_vysokogornye_alpy' => 70,
        'step5_ozera_vodopady' => 70,
        'step5_sredizemnomorskiy_vayb' => 15,
        'step5_alpiyskie_luga' => 75,
        'step5_istoricheskie_goroda' => 55,
        'step5_vinodelcheskie_terrasy' => 25,
        'step6_peshie_progulki' => 88,
        'step6_gornye_lyzhi' => 70,
        'step6_panoramnye_poezda' => 80,
        'step6_ekskursii_muzei' => 55,
        'step6_gastronomiya' => 70,
        'step6_spa_ozdorovlenie' => 55,
        'step7_byudzhetnyy' => 45,
        'step7_standartnyy' => 82,
        'step7_povyshennyy_komfort' => 82,
        'step7_premialnyy' => 70,
        'step8_legkiy_marshrut' => 75,
        'step8_vizitnye_kartochki' => 80,
        'step8_zashchita_ot_nepogody' => 70,
        'step8_uedinennost' => 55,
        'step8_prostaya_logistika' => 80,
        'step8_net_pozhelaniy' => 78,
    ];
}

/**
 * @return array<string,int>
 */
function templateScores(string $template): array
{
    $base = neutralBase();

    $map = [
        'jungfrau_hub' => [
            'step1_vesnoy' => 86, 'step1_letom' => 96, 'step1_osenyu' => 90, 'step1_zimoy' => 78,
            'step2_dnya_1_2' => 72, 'step2_dnya_3_4' => 94, 'step2_dney_5_7' => 96, 'step2_dney_8_10' => 82, 'step2_bolee_10_dney' => 62,
            'step3_solo' => 88, 'step3_para' => 94, 'step3_kompaniya_druzei' => 90, 'step3_semya_do_6' => 78, 'step3_semya_ot_7' => 92,
            'step4_obshchestvennyy_transport' => 94, 'step4_arendovannyy_avtomobil' => 62, 'step4_sochetanie' => 88,
            'step5_vysokogornye_alpy' => 90, 'step5_ozera_vodopady' => 94, 'step5_sredizemnomorskiy_vayb' => 8,
            'step5_alpiyskie_luga' => 88, 'step5_istoricheskie_goroda' => 42, 'step5_vinodelcheskie_terrasy' => 18,
            'step6_peshie_progulki' => 95, 'step6_gornye_lyzhi' => 82, 'step6_panoramnye_poezda' => 97,
            'step6_ekskursii_muzei' => 58, 'step6_gastronomiya' => 72, 'step6_spa_ozdorovlenie' => 55,
            'step7_byudzhetnyy' => 40, 'step7_standartnyy' => 82, 'step7_povyshennyy_komfort' => 88, 'step7_premialnyy' => 80,
            'step8_legkiy_marshrut' => 78, 'step8_vizitnye_kartochki' => 96, 'step8_zashchita_ot_nepogody' => 72,
            'step8_uedinennost' => 35, 'step8_prostaya_logistika' => 90, 'step8_net_pozhelaniy' => 88,
        ],
        'jungfrau_valley' => [
            'step1_vesnoy' => 84, 'step1_letom' => 95, 'step1_osenyu' => 90, 'step1_zimoy' => 82,
            'step2_dnya_1_2' => 70, 'step2_dnya_3_4' => 92, 'step2_dney_5_7' => 94, 'step2_dney_8_10' => 78, 'step2_bolee_10_dney' => 55,
            'step3_solo' => 85, 'step3_para' => 93, 'step3_kompaniya_druzei' => 86, 'step3_semya_do_6' => 70, 'step3_semya_ot_7' => 90,
            'step4_obshchestvennyy_transport' => 96, 'step4_arendovannyy_avtomobil' => 35, 'step4_sochetanie' => 70,
            'step5_vysokogornye_alpy' => 94, 'step5_ozera_vodopady' => 92, 'step5_sredizemnomorskiy_vayb' => 5,
            'step5_alpiyskie_luga' => 90, 'step5_istoricheskie_goroda' => 28, 'step5_vinodelcheskie_terrasy' => 12,
            'step6_peshie_progulki' => 97, 'step6_gornye_lyzhi' => 86, 'step6_panoramnye_poezda' => 96,
            'step6_ekskursii_muzei' => 40, 'step6_gastronomiya' => 68, 'step6_spa_ozdorovlenie' => 48,
            'step7_byudzhetnyy' => 36, 'step7_standartnyy' => 78, 'step7_povyshennyy_komfort' => 86, 'step7_premialnyy' => 82,
            'step8_legkiy_marshrut' => 72, 'step8_vizitnye_kartochki' => 95, 'step8_zashchita_ot_nepogody' => 58,
            'step8_uedinennost' => 48, 'step8_prostaya_logistika' => 82, 'step8_net_pozhelaniy' => 84,
        ],
        'high_alpine_ski' => [
            'step1_vesnoy' => 72, 'step1_letom' => 88, 'step1_osenyu' => 76, 'step1_zimoy' => 97,
            'step2_dnya_1_2' => 55, 'step2_dnya_3_4' => 82, 'step2_dney_5_7' => 96, 'step2_dney_8_10' => 92, 'step2_bolee_10_dney' => 78,
            'step3_solo' => 80, 'step3_para' => 92, 'step3_kompaniya_druzei' => 90, 'step3_semya_do_6' => 58, 'step3_semya_ot_7' => 84,
            'step4_obshchestvennyy_transport' => 92, 'step4_arendovannyy_avtomobil' => 28, 'step4_sochetanie' => 55,
            'step5_vysokogornye_alpy' => 97, 'step5_ozera_vodopady' => 55, 'step5_sredizemnomorskiy_vayb' => 5,
            'step5_alpiyskie_luga' => 82, 'step5_istoricheskie_goroda' => 30, 'step5_vinodelcheskie_terrasy' => 12,
            'step6_peshie_progulki' => 92, 'step6_gornye_lyzhi' => 97, 'step6_panoramnye_poezda' => 94,
            'step6_ekskursii_muzei' => 42, 'step6_gastronomiya' => 78, 'step6_spa_ozdorovlenie' => 58,
            'step7_byudzhetnyy' => 32, 'step7_standartnyy' => 70, 'step7_povyshennyy_komfort' => 90, 'step7_premialnyy' => 92,
            'step8_legkiy_marshrut' => 62, 'step8_vizitnye_kartochki' => 96, 'step8_zashchita_ot_nepogody' => 65,
            'step8_uedinennost' => 55, 'step8_prostaya_logistika' => 78, 'step8_net_pozhelaniy' => 82,
        ],
        'premium_ski' => [
            'step1_vesnoy' => 70, 'step1_letom' => 85, 'step1_osenyu' => 74, 'step1_zimoy' => 96,
            'step2_dnya_1_2' => 48, 'step2_dnya_3_4' => 78, 'step2_dney_5_7' => 95, 'step2_dney_8_10' => 94, 'step2_bolee_10_dney' => 85,
            'step3_solo' => 72, 'step3_para' => 94, 'step3_kompaniya_druzei' => 88, 'step3_semya_do_6' => 55, 'step3_semya_ot_7' => 78,
            'step4_obshchestvennyy_transport' => 72, 'step4_arendovannyy_avtomobil' => 70, 'step4_sochetanie' => 86,
            'step5_vysokogornye_alpy' => 92, 'step5_ozera_vodopady' => 62, 'step5_sredizemnomorskiy_vayb' => 10,
            'step5_alpiyskie_luga' => 80, 'step5_istoricheskie_goroda' => 35, 'step5_vinodelcheskie_terrasy' => 22,
            'step6_peshie_progulki' => 86, 'step6_gornye_lyzhi' => 96, 'step6_panoramnye_poezda' => 78,
            'step6_ekskursii_muzei' => 48, 'step6_gastronomiya' => 90, 'step6_spa_ozdorovlenie' => 82,
            'step7_byudzhetnyy' => 18, 'step7_standartnyy' => 48, 'step7_povyshennyy_komfort' => 88, 'step7_premialnyy' => 98,
            'step8_legkiy_marshrut' => 70, 'step8_vizitnye_kartochki' => 90, 'step8_zashchita_ot_nepogody' => 78,
            'step8_uedinennost' => 60, 'step8_prostaya_logistika' => 70, 'step8_net_pozhelaniy' => 75,
        ],
        'ticino_mediterranean' => [
            'step1_vesnoy' => 90, 'step1_letom' => 96, 'step1_osenyu' => 88, 'step1_zimoy' => 42,
            'step2_dnya_1_2' => 78, 'step2_dnya_3_4' => 94, 'step2_dney_5_7' => 92, 'step2_dney_8_10' => 78, 'step2_bolee_10_dney' => 60,
            'step3_solo' => 85, 'step3_para' => 96, 'step3_kompaniya_druzei' => 88, 'step3_semya_do_6' => 80, 'step3_semya_ot_7' => 86,
            'step4_obshchestvennyy_transport' => 82, 'step4_arendovannyy_avtomobil' => 88, 'step4_sochetanie' => 92,
            'step5_vysokogornye_alpy' => 45, 'step5_ozera_vodopady' => 94, 'step5_sredizemnomorskiy_vayb' => 95,
            'step5_alpiyskie_luga' => 55, 'step5_istoricheskie_goroda' => 78, 'step5_vinodelcheskie_terrasy' => 72,
            'step6_peshie_progulki' => 88, 'step6_gornye_lyzhi' => 22, 'step6_panoramnye_poezda' => 70,
            'step6_ekskursii_muzei' => 72, 'step6_gastronomiya' => 92, 'step6_spa_ozdorovlenie' => 70,
            'step7_byudzhetnyy' => 42, 'step7_standartnyy' => 84, 'step7_povyshennyy_komfort' => 90, 'step7_premialnyy' => 82,
            'step8_legkiy_marshrut' => 90, 'step8_vizitnye_kartochki' => 88, 'step8_zashchita_ot_nepogody' => 75,
            'step8_uedinennost' => 48, 'step8_prostaya_logistika' => 85, 'step8_net_pozhelaniy' => 86,
        ],
        'lake_geneva' => [
            'step1_vesnoy' => 88, 'step1_letom' => 94, 'step1_osenyu' => 90, 'step1_zimoy' => 55,
            'step2_dnya_1_2' => 85, 'step2_dnya_3_4' => 94, 'step2_dney_5_7' => 88, 'step2_dney_8_10' => 70, 'step2_bolee_10_dney' => 52,
            'step3_solo' => 86, 'step3_para' => 95, 'step3_kompaniya_druzei' => 82, 'step3_semya_do_6' => 78, 'step3_semya_ot_7' => 84,
            'step4_obshchestvennyy_transport' => 90, 'step4_arendovannyy_avtomobil' => 78, 'step4_sochetanie' => 90,
            'step5_vysokogornye_alpy' => 48, 'step5_ozera_vodopady' => 92, 'step5_sredizemnomorskiy_vayb' => 78,
            'step5_alpiyskie_luga' => 55, 'step5_istoricheskie_goroda' => 82, 'step5_vinodelcheskie_terrasy' => 88,
            'step6_peshie_progulki' => 82, 'step6_gornye_lyzhi' => 40, 'step6_panoramnye_poezda' => 78,
            'step6_ekskursii_muzei' => 85, 'step6_gastronomiya' => 90, 'step6_spa_ozdorovlenie' => 68,
            'step7_byudzhetnyy' => 38, 'step7_standartnyy' => 80, 'step7_povyshennyy_komfort' => 90, 'step7_premialnyy' => 86,
            'step8_legkiy_marshrut' => 92, 'step8_vizitnye_kartochki' => 90, 'step8_zashchita_ot_nepogody' => 82,
            'step8_uedinennost' => 40, 'step8_prostaya_logistika' => 92, 'step8_net_pozhelaniy' => 88,
        ],
        'big_city' => [
            'step1_vesnoy' => 86, 'step1_letom' => 90, 'step1_osenyu' => 88, 'step1_zimoy' => 78,
            'step2_dnya_1_2' => 94, 'step2_dnya_3_4' => 92, 'step2_dney_5_7' => 78, 'step2_dney_8_10' => 52, 'step2_bolee_10_dney' => 35,
            'step3_solo' => 92, 'step3_para' => 88, 'step3_kompaniya_druzei' => 86, 'step3_semya_do_6' => 75, 'step3_semya_ot_7' => 82,
            'step4_obshchestvennyy_transport' => 98, 'step4_arendovannyy_avtomobil' => 45, 'step4_sochetanie' => 72,
            'step5_vysokogornye_alpy' => 28, 'step5_ozera_vodopady' => 70, 'step5_sredizemnomorskiy_vayb' => 35,
            'step5_alpiyskie_luga' => 30, 'step5_istoricheskie_goroda' => 88, 'step5_vinodelcheskie_terrasy' => 25,
            'step6_peshie_progulki' => 72, 'step6_gornye_lyzhi' => 25, 'step6_panoramnye_poezda' => 55,
            'step6_ekskursii_muzei' => 96, 'step6_gastronomiya' => 92, 'step6_spa_ozdorovlenie' => 58,
            'step7_byudzhetnyy' => 42, 'step7_standartnyy' => 82, 'step7_povyshennyy_komfort' => 90, 'step7_premialnyy' => 88,
            'step8_legkiy_marshrut' => 95, 'step8_vizitnye_kartochki' => 90, 'step8_zashchita_ot_nepogody' => 96,
            'step8_uedinennost' => 22, 'step8_prostaya_logistika' => 96, 'step8_net_pozhelaniy' => 90,
        ],
        'capital_heritage' => [
            'step1_vesnoy' => 88, 'step1_letom' => 90, 'step1_osenyu' => 90, 'step1_zimoy' => 70,
            'step2_dnya_1_2' => 92, 'step2_dnya_3_4' => 94, 'step2_dney_5_7' => 75, 'step2_dney_8_10' => 48, 'step2_bolee_10_dney' => 32,
            'step3_solo' => 90, 'step3_para' => 92, 'step3_kompaniya_druzei' => 80, 'step3_semya_do_6' => 78, 'step3_semya_ot_7' => 84,
            'step4_obshchestvennyy_transport' => 96, 'step4_arendovannyy_avtomobil' => 55, 'step4_sochetanie' => 80,
            'step5_vysokogornye_alpy' => 35, 'step5_ozera_vodopady' => 55, 'step5_sredizemnomorskiy_vayb' => 18,
            'step5_alpiyskie_luga' => 48, 'step5_istoricheskie_goroda' => 97, 'step5_vinodelcheskie_terrasy' => 35,
            'step6_peshie_progulki' => 80, 'step6_gornye_lyzhi' => 28, 'step6_panoramnye_poezda' => 52,
            'step6_ekskursii_muzei' => 95, 'step6_gastronomiya' => 82, 'step6_spa_ozdorovlenie' => 48,
            'step7_byudzhetnyy' => 48, 'step7_standartnyy' => 88, 'step7_povyshennyy_komfort' => 86, 'step7_premialnyy' => 75,
            'step8_legkiy_marshrut' => 94, 'step8_vizitnye_kartochki' => 92, 'step8_zashchita_ot_nepogody' => 92,
            'step8_uedinennost' => 40, 'step8_prostaya_logistika' => 95, 'step8_net_pozhelaniy' => 88,
        ],
        'heritage_town' => [
            'step1_vesnoy' => 86, 'step1_letom' => 90, 'step1_osenyu' => 88, 'step1_zimoy' => 58,
            'step2_dnya_1_2' => 96, 'step2_dnya_3_4' => 82, 'step2_dney_5_7' => 55, 'step2_dney_8_10' => 30, 'step2_bolee_10_dney' => 18,
            'step3_solo' => 88, 'step3_para' => 92, 'step3_kompaniya_druzei' => 75, 'step3_semya_do_6' => 80, 'step3_semya_ot_7' => 82,
            'step4_obshchestvennyy_transport' => 85, 'step4_arendovannyy_avtomobil' => 80, 'step4_sochetanie' => 88,
            'step5_vysokogornye_alpy' => 22, 'step5_ozera_vodopady' => 55, 'step5_sredizemnomorskiy_vayb' => 15,
            'step5_alpiyskie_luga' => 55, 'step5_istoricheskie_goroda' => 96, 'step5_vinodelcheskie_terrasy' => 45,
            'step6_peshie_progulki' => 78, 'step6_gornye_lyzhi' => 15, 'step6_panoramnye_poezda' => 40,
            'step6_ekskursii_muzei' => 90, 'step6_gastronomiya' => 88, 'step6_spa_ozdorovlenie' => 35,
            'step7_byudzhetnyy' => 55, 'step7_standartnyy' => 88, 'step7_povyshennyy_komfort' => 78, 'step7_premialnyy' => 58,
            'step8_legkiy_marshrut' => 96, 'step8_vizitnye_kartochki' => 90, 'step8_zashchita_ot_nepogody' => 75,
            'step8_uedinennost' => 50, 'step8_prostaya_logistika' => 90, 'step8_net_pozhelaniy' => 82,
        ],
        'rhine_falls' => [
            'step1_vesnoy' => 85, 'step1_letom' => 92, 'step1_osenyu' => 86, 'step1_zimoy' => 55,
            'step2_dnya_1_2' => 96, 'step2_dnya_3_4' => 78, 'step2_dney_5_7' => 48, 'step2_dney_8_10' => 25, 'step2_bolee_10_dney' => 15,
            'step3_solo' => 86, 'step3_para' => 90, 'step3_kompaniya_druzei' => 82, 'step3_semya_do_6' => 88, 'step3_semya_ot_7' => 90,
            'step4_obshchestvennyy_transport' => 88, 'step4_arendovannyy_avtomobil' => 85, 'step4_sochetanie' => 90,
            'step5_vysokogornye_alpy' => 12, 'step5_ozera_vodopady' => 96, 'step5_sredizemnomorskiy_vayb' => 10,
            'step5_alpiyskie_luga' => 40, 'step5_istoricheskie_goroda' => 88, 'step5_vinodelcheskie_terrasy' => 35,
            'step6_peshie_progulki' => 80, 'step6_gornye_lyzhi' => 8, 'step6_panoramnye_poezda' => 45,
            'step6_ekskursii_muzei' => 78, 'step6_gastronomiya' => 70, 'step6_spa_ozdorovlenie' => 30,
            'step7_byudzhetnyy' => 58, 'step7_standartnyy' => 88, 'step7_povyshennyy_komfort' => 75, 'step7_premialnyy' => 55,
            'step8_legkiy_marshrut' => 95, 'step8_vizitnye_kartochki' => 92, 'step8_zashchita_ot_nepogody' => 68,
            'step8_uedinennost' => 35, 'step8_prostaya_logistika' => 92, 'step8_net_pozhelaniy' => 85,
        ],
        'spa_valais' => [
            'step1_vesnoy' => 82, 'step1_letom' => 88, 'step1_osenyu' => 84, 'step1_zimoy' => 86,
            'step2_dnya_1_2' => 70, 'step2_dnya_3_4' => 92, 'step2_dney_5_7' => 94, 'step2_dney_8_10' => 85, 'step2_bolee_10_dney' => 70,
            'step3_solo' => 80, 'step3_para' => 94, 'step3_kompaniya_druzei' => 75, 'step3_semya_do_6' => 78, 'step3_semya_ot_7' => 82,
            'step4_obshchestvennyy_transport' => 78, 'step4_arendovannyy_avtomobil' => 88, 'step4_sochetanie' => 90,
            'step5_vysokogornye_alpy' => 78, 'step5_ozera_vodopady' => 55, 'step5_sredizemnomorskiy_vayb' => 12,
            'step5_alpiyskie_luga' => 82, 'step5_istoricheskie_goroda' => 35, 'step5_vinodelcheskie_terrasy' => 40,
            'step6_peshie_progulki' => 85, 'step6_gornye_lyzhi' => 72, 'step6_panoramnye_poezda' => 70,
            'step6_ekskursii_muzei' => 40, 'step6_gastronomiya' => 72, 'step6_spa_ozdorovlenie' => 98,
            'step7_byudzhetnyy' => 40, 'step7_standartnyy' => 80, 'step7_povyshennyy_komfort' => 90, 'step7_premialnyy' => 82,
            'step8_legkiy_marshrut' => 88, 'step8_vizitnye_kartochki' => 78, 'step8_zashchita_ot_nepogody' => 92,
            'step8_uedinennost' => 65, 'step8_prostaya_logistika' => 78, 'step8_net_pozhelaniy' => 82,
        ],
        'alpine_resort' => [
            'step1_vesnoy' => 80, 'step1_letom' => 92, 'step1_osenyu' => 84, 'step1_zimoy' => 90,
            'step2_dnya_1_2' => 60, 'step2_dnya_3_4' => 88, 'step2_dney_5_7' => 95, 'step2_dney_8_10' => 88, 'step2_bolee_10_dney' => 72,
            'step3_solo' => 78, 'step3_para' => 90, 'step3_kompaniya_druzei' => 88, 'step3_semya_do_6' => 75, 'step3_semya_ot_7' => 88,
            'step4_obshchestvennyy_transport' => 80, 'step4_arendovannyy_avtomobil' => 82, 'step4_sochetanie' => 90,
            'step5_vysokogornye_alpy' => 88, 'step5_ozera_vodopady' => 70, 'step5_sredizemnomorskiy_vayb' => 8,
            'step5_alpiyskie_luga' => 90, 'step5_istoricheskie_goroda' => 32, 'step5_vinodelcheskie_terrasy' => 20,
            'step6_peshie_progulki' => 94, 'step6_gornye_lyzhi' => 90, 'step6_panoramnye_poezda' => 82,
            'step6_ekskursii_muzei' => 42, 'step6_gastronomiya' => 75, 'step6_spa_ozdorovlenie' => 65,
            'step7_byudzhetnyy' => 38, 'step7_standartnyy' => 80, 'step7_povyshennyy_komfort' => 88, 'step7_premialnyy' => 80,
            'step8_legkiy_marshrut' => 78, 'step8_vizitnye_kartochki' => 82, 'step8_zashchita_ot_nepogody' => 70,
            'step8_uedinennost' => 62, 'step8_prostaya_logistika' => 78, 'step8_net_pozhelaniy' => 82,
        ],
        'lake_thun_brienz' => [
            'step1_vesnoy' => 86, 'step1_letom' => 95, 'step1_osenyu' => 90, 'step1_zimoy' => 58,
            'step2_dnya_1_2' => 88, 'step2_dnya_3_4' => 94, 'step2_dney_5_7' => 85, 'step2_dney_8_10' => 62, 'step2_bolee_10_dney' => 42,
            'step3_solo' => 86, 'step3_para' => 94, 'step3_kompaniya_druzei' => 82, 'step3_semya_do_6' => 82, 'step3_semya_ot_7' => 88,
            'step4_obshchestvennyy_transport' => 92, 'step4_arendovannyy_avtomobil' => 78, 'step4_sochetanie' => 90,
            'step5_vysokogornye_alpy' => 68, 'step5_ozera_vodopady' => 96, 'step5_sredizemnomorskiy_vayb' => 12,
            'step5_alpiyskie_luga' => 82, 'step5_istoricheskie_goroda' => 75, 'step5_vinodelcheskie_terrasy' => 22,
            'step6_peshie_progulki' => 90, 'step6_gornye_lyzhi' => 45, 'step6_panoramnye_poezda' => 90,
            'step6_ekskursii_muzei' => 72, 'step6_gastronomiya' => 75, 'step6_spa_ozdorovlenie' => 48,
            'step7_byudzhetnyy' => 48, 'step7_standartnyy' => 86, 'step7_povyshennyy_komfort' => 84, 'step7_premialnyy' => 70,
            'step8_legkiy_marshrut' => 90, 'step8_vizitnye_kartochki' => 86, 'step8_zashchita_ot_nepogody' => 72,
            'step8_uedinennost' => 52, 'step8_prostaya_logistika' => 90, 'step8_net_pozhelaniy' => 86,
        ],
        'wine_valley' => [
            'step1_vesnoy' => 88, 'step1_letom' => 92, 'step1_osenyu' => 95, 'step1_zimoy' => 48,
            'step2_dnya_1_2' => 88, 'step2_dnya_3_4' => 92, 'step2_dney_5_7' => 82, 'step2_dney_8_10' => 58, 'step2_bolee_10_dney' => 38,
            'step3_solo' => 84, 'step3_para' => 94, 'step3_kompaniya_druzei' => 80, 'step3_semya_do_6' => 72, 'step3_semya_ot_7' => 78,
            'step4_obshchestvennyy_transport' => 82, 'step4_arendovannyy_avtomobil' => 90, 'step4_sochetanie' => 92,
            'step5_vysokogornye_alpy' => 55, 'step5_ozera_vodopady' => 50, 'step5_sredizemnomorskiy_vayb' => 45,
            'step5_alpiyskie_luga' => 60, 'step5_istoricheskie_goroda' => 85, 'step5_vinodelcheskie_terrasy' => 96,
            'step6_peshie_progulki' => 82, 'step6_gornye_lyzhi' => 35, 'step6_panoramnye_poezda' => 55,
            'step6_ekskursii_muzei' => 78, 'step6_gastronomiya' => 94, 'step6_spa_ozdorovlenie' => 45,
            'step7_byudzhetnyy' => 48, 'step7_standartnyy' => 86, 'step7_povyshennyy_komfort' => 85, 'step7_premialnyy' => 72,
            'step8_legkiy_marshrut' => 88, 'step8_vizitnye_kartochki' => 80, 'step8_zashchita_ot_nepogody' => 70,
            'step8_uedinennost' => 55, 'step8_prostaya_logistika' => 85, 'step8_net_pozhelaniy' => 82,
        ],
    ];

    if (! isset($map[$template])) {
        throw new RuntimeException("Unknown template: {$template}");
    }

    return array_merge($base, $map[$template]);
}

/**
 * @return array<int, array{
 *   name:string,
 *   name_ru:string,
 *   template:string,
 *   tags:array<int,string>,
 *   intro:string,
 *   scores?:array<string,int>,
 *   descriptions?:array<string,string>
 * }>
 */
function regionDefinitions(): array
{
    return [
        2 => [
            'name' => 'Interlaken',
            'name_ru' => 'Интерлакен',
            'template' => 'jungfrau_hub',
            'tags' => ['хаб Юнгфрау', 'Тунское и Бриенцское озёра', 'параглайдинг', 'база для day-trip'],
            'intro' => '<p><strong>Интерлакен (Interlaken)</strong> — туристический хаб между Тунским и Бриенцским озёрами у ворот региона Юнгфрау.</p><p>Отсюда удобно ездить в Гриндельвальд, Лаутербруннен и на Юнгфрауйох; сам город — отели, набережные и активный отдых, а не «тихая альпийская деревня».</p>',
            'scores' => [
                'step5_ozera_vodopady' => 95, 'step5_vysokogornye_alpy' => 88, 'step8_uedinennost' => 28,
                'step6_gornye_lyzhi' => 78, 'step4_arendovannyy_avtomobil' => 70,
            ],
            'descriptions' => [
                'step1_letom' => 'Летом Интерлакен на пике: озёрные круизы, хайкинг, параглайдинг и полная работа железных дорог Юнгфрау — главный сезон хаба.',
                'step5_ozera_vodopady' => 'Город буквально «между озёрами»; пароходы по Туну и Бриенцу плюс выезды к водопадам Лаутербруннена — озёрно-водопадный профиль максимальный.',
                'step5_vysokogornye_alpy' => 'Сами вершины — day-trip’ом (Юнгфрауйох, Шилтхорн), но логистика хаба к высокогорью одна из лучших в стране.',
                'step5_sredizemnomorskiy_vayb' => 'Пальм и южного вайба нет — классический бернский Оберланд, не Тичино.',
                'step6_panoramnye_poezda' => 'Стартовая точка для Jungfrau Railway, Schynige Platte и озёрных пароходов — один из сильнейших панорамных хабов.',
                'step8_uedinennost' => 'Летом и в пик сезона людно: хаб с потоком туристов, уединение — только вне центра или рано утром.',
            ],
        ],

        8 => [
            'name' => 'Zermatt',
            'name_ru' => 'Церматт',
            'template' => 'high_alpine_ski',
            'tags' => ['Маттерхорн', 'car-free', 'ледник', 'топ-ски'],
            'intro' => '<p><strong>Церматт (Zermatt)</strong> — car-free курорт у Маттерхорна, один из самых знаковых высокогорных образов Швейцарии.</p><p>Зимой — мощный ски-пасс и ледниковые зоны; летом — хайкинг и зубчатые дороги к ледникам. Премиальный, фотогеничный и сильно завязанный на общественный горный транспорт.</p>',
            'scores' => [
                'step1_zimoy' => 98, 'step5_vysokogornye_alpy' => 98, 'step5_sredizemnomorskiy_vayb' => 5,
                'step5_vinodelcheskie_terrasy' => 10, 'step5_ozera_vodopady' => 48,
                'step4_obshchestvennyy_transport' => 96, 'step4_arendovannyy_avtomobil' => 8,
                'step6_gornye_lyzhi' => 98, 'step6_panoramnye_poezda' => 96, 'step6_spa_ozdorovlenie' => 62,
                'step7_byudzhetnyy' => 22, 'step7_premialnyy' => 96, 'step8_vizitnye_kartochki' => 99,
                'step8_uedinennost' => 42, 'step3_semya_do_6' => 62,
            ],
            'descriptions' => [
                'step1_zimoy' => 'Зима — главный сезон: длинные трассы, надёжный снег и ледниковые зоны; Церматт входит в мировой ski-топ.',
                'step5_vysokogornye_alpy' => 'Маттерхорн и ледниковое кольцо — эталон высокогорья; мало где в Швейцарии альпийский силуэт настолько узнаваем.',
                'step5_sredizemnomorskiy_vayb' => 'Южного/пальмового вайба нет совсем — чистый валезийский высокогорный пейзаж.',
                'step5_vinodelcheskie_terrasy' => 'Виноградники Вале — ниже по долине (Сьер/Сьон), не у самого Маттерхорна; здесь акцент на пиках, не на террасах.',
                'step4_obshchestvennyy_transport' => 'Авто в деревню не пускают: электротакси, поезда Gornergrat и подъёмники — ОТ в горах работает идеально.',
                'step4_arendovannyy_avtomobil' => 'Машину оставляют в Таше: в самом Церматте авто почти бесполезно и противоречит модели курорта.',
                'step6_gornye_lyzhi' => 'Один из лучших ски-регионов Европы: ледник, связки трасс и сезон заметно длиннее среднего.',
                'step7_byudzhetnyy' => 'Очень дорогой курорт: жильё, еда и подъёмники бьют по бюджету сильнее большинства регионов.',
                'step7_premialnyy' => 'Люкс-отели, вид на Маттерхорн и премиальный сервис — естественная ценовая полка Церматта.',
                'step8_vizitnye_kartochki' => 'Маттерхорн — абсолютная визитная карточка страны; для «открыточного» списка почти максимум.',
            ],
        ],

        9 => [
            'name' => 'Lugano',
            'name_ru' => 'Лугано',
            'template' => 'ticino_mediterranean',
            'tags' => ['Тичино', 'озеро Лугано', 'пальмы', 'итальянский вайб'],
            'intro' => '<p><strong>Лугано (Lugano)</strong> — главный город итальянской Швейцарии на озере среди холмов с пальмами и южной атмосферой.</p><p>Здесь силён «средиземноморский» отдых: набережные, кафе, прогулки на Monte Brè / San Salvatore, мягкий климат. Это не лыжный и не высокогорный профиль.</p>',
            'scores' => [
                'step5_sredizemnomorskiy_vayb' => 96, 'step5_ozera_vodopady' => 93, 'step5_vysokogornye_alpy' => 38,
                'step1_zimoy' => 40, 'step6_gornye_lyzhi' => 18, 'step6_gastronomiya' => 93,
                'step5_istoricheskie_goroda' => 80, 'step7_premialnyy' => 84,
            ],
            'descriptions' => [
                'step5_sredizemnomorskiy_vayb' => 'Пальмы, итальянский язык и южный ритм набережной — один из самых «средиземноморских» уголков Швейцарии.',
                'step1_zimoy' => 'Зима мягкая и без большого ski-фокуса; регион живёт прогулками и городом, а не альпийским снегом.',
                'step6_gornye_lyzhi' => 'Серьёзных лыжных курортов у Лугано нет — зимой это не выбор для горнолыжников.',
                'step6_gastronomiya' => 'Тичинская и итальянская кухня, рыба озера, эспрессо-культура — гастрономия здесь сильнее среднего по стране.',
                'step5_vysokogornye_alpy' => 'Вокруг холмы и предальпы; ледники и четырёхтысячники — не про Лугано.',
            ],
        ],

        10 => [
            'name' => 'Bern',
            'name_ru' => 'Берн',
            'template' => 'capital_heritage',
            'tags' => ['столица', 'UNESCO старый город', 'Аре', 'музеи'],
            'intro' => '<p><strong>Берн (Bern)</strong> — федеральная столица с аркадами старого города ЮНЕСКО над рекой Аре.</p><p>Сильная сторона — история, музеи и комфортные городские прогулки; высокогорье и лыжи — только выездами в Оберланд.</p>',
            'scores' => [
                'step5_istoricheskie_goroda' => 98, 'step5_ozera_vodopady' => 48, 'step6_ekskursii_muzei' => 96,
                'step6_gornye_lyzhi' => 22, 'step5_sredizemnomorskiy_vayb' => 12, 'step8_vizitnye_kartochki' => 90,
            ],
            'descriptions' => [
                'step5_istoricheskie_goroda' => 'Аркады, часовая башня Zytglogge и медвежий ров — один из самых цельных средневековых центров страны.',
                'step6_ekskursii_muzei' => 'Zentrum Paul Klee, исторический музей и сам старый город дают плотную культурную программу даже в дождь.',
                'step5_vysokogornye_alpy' => 'Альпы видны на горизонте, но сам Берн — равнинно-холмистая столица, не горный курорт.',
                'step6_gornye_lyzhi' => 'Для лыж нужны выезды (Гриндельвальд, Адельбоден и др.); в городе снежного спорта почти нет.',
            ],
        ],

        11 => [
            'name' => 'Zurich',
            'name_ru' => 'Цюрих',
            'template' => 'big_city',
            'tags' => ['крупнейший город', 'Цюрихское озеро', 'банки/культура', 'хаб ОТ'],
            'intro' => '<p><strong>Цюрих (Zurich)</strong> — крупнейший город страны: озеро, Старый город, музеи и один из лучших транспортных хабов Европы.</p><p>Идеален для коротких городских поездок и культуры; «дикие Альпы» и лыжи — только за его пределами.</p>',
            'scores' => [
                'step5_ozera_vodopady' => 82, 'step5_istoricheskie_goroda' => 86, 'step5_vysokogornye_alpy' => 22,
                'step6_gastronomiya' => 94, 'step6_ekskursii_muzei' => 97, 'step7_premialnyy' => 92,
                'step7_byudzhetnyy' => 35, 'step1_zimoy' => 80, 'step5_sredizemnomorskiy_vayb' => 28,
            ],
            'descriptions' => [
                'step6_ekskursii_muzei' => 'Kunsthaus, Landesmuseum, улица Банхофштрассе и Старый город — культурный набор мирового уровня.',
                'step5_ozera_vodopady' => 'Цюрихское озеро и набережные — сильный городской озёрный акцент; водопады Рейна — уже day-trip в Шаффхаузен.',
                'step5_vysokogornye_alpy' => 'Горы далеко; Цюрих — урбанистика и озеро, не высокогорный курорт.',
                'step7_premialnyy' => 'Люкс-отели, высокий ресторанный чек и шопинг — премиальный городской формат здесь естественен.',
                'step4_obshchestvennyy_transport' => 'Трамваи, S-Bahn и аэропорт — один из самых удобных транспортных узлов для туриста без машины.',
            ],
        ],

        12 => [
            'name' => 'Geneva',
            'name_ru' => 'Женева',
            'template' => 'big_city',
            'tags' => ['Женевское озеро', 'фонтан Jet d’Eau', 'международный город', 'фр. Швейцария'],
            'intro' => '<p><strong>Женева (Geneva)</strong> — международный город на западном краю Женевского озера с фонтаном Jet d’Eau и сильным французским влиянием.</p><p>Подходит для города, набережных и коротких культурных визитов; Альпы и лыжи — выездом (Шамони/внутренние курорты).</p>',
            'scores' => [
                'step5_ozera_vodopady' => 88, 'step5_sredizemnomorskiy_vayb' => 55, 'step5_istoricheskie_goroda' => 82,
                'step5_vysokogornye_alpy' => 30, 'step6_ekskursii_muzei' => 90, 'step6_gastronomiya' => 90,
                'step7_premialnyy' => 90, 'step7_byudzhetnyy' => 32, 'step5_vinodelcheskie_terrasy' => 48,
                'step8_vizitnye_kartochki' => 88,
            ],
            'descriptions' => [
                'step5_ozera_vodopady' => 'Женевское озеро и Jet d’Eau — главный природный кадр; прогулки и круизы сильнее, чем «дикие» водопады.',
                'step5_sredizemnomorskiy_vayb' => 'Климат мягче альпийского mittelland, есть южный оттенок набережной, но это не Тичино с пальмовым вайбом.',
                'step6_ekskursii_muzei' => 'ООН-квартал, музеи и Старый город дают насыщенную городскую программу при любой погоде.',
                'step5_vysokogornye_alpy' => 'Пики видны в ясную погоду, но женевский опыт — город и озеро; высокогорье — отдельно.',
            ],
        ],

        13 => [
            'name' => 'Basel',
            'name_ru' => 'Базель',
            'template' => 'big_city',
            'tags' => [' Рейн', 'арт-музеи', 'граница DE/FR', 'городской'],
            'intro' => '<p><strong>Базель (Basel)</strong> — рейнский город искусства на стыке Швейцарии, Германии и Франции.</p><p>Сильны музеи (Kunstmuseum, Fondation Beyeler), ярмарки и городская культура; озёр и высокогорья здесь почти нет.</p>',
            'scores' => [
                'step5_ozera_vodopady' => 35, 'step5_vysokogornye_alpy' => 15, 'step5_istoricheskie_goroda' => 90,
                'step6_ekskursii_muzei' => 98, 'step6_gastronomiya' => 88, 'step6_gornye_lyzhi' => 12,
                'step5_sredizemnomorskiy_vayb' => 18, 'step8_vizitnye_kartochki' => 78,
                'step1_letom' => 88, 'step5_alpiyskie_luga' => 22,
            ],
            'descriptions' => [
                'step6_ekskursii_muzei' => 'Один из лучших арт-кластеров страны: Kunstmuseum, Beyeler, Tinguely — музеи здесь главная причина приезда.',
                'step5_ozera_vodopady' => 'Рейн и набережные красивы, но озёрно-водопадного альпийского пейзажа как в Оберланде нет.',
                'step5_vysokogornye_alpy' => 'Базель в mittelland у границы; Альпы — далёкая поездка, не локальный пейзаж.',
                'step6_gornye_lyzhi' => 'Лыжного курорта в городе нет; зимний туризм — культура и город, не склоны.',
            ],
        ],

        14 => [
            'name' => 'Montreux',
            'name_ru' => 'Монтрё',
            'template' => 'lake_geneva',
            'tags' => ['Ривьера', 'замок Шильон', 'набережная', 'мягкий климат'],
            'intro' => '<p><strong>Монтрё (Montreux)</strong> — жемчужина Швейцарской Ривьеры на востоке Женевского озера с замком Шильон и знаменитой набережной.</p><p>Мягкий климат, променады и музыкальные фестивали; лыжи — выездом в соседние курорты.</p>',
            'scores' => [
                'step5_sredizemnomorskiy_vayb' => 82, 'step5_ozera_vodopady' => 93, 'step5_istoricheskie_goroda' => 78,
                'step5_vinodelcheskie_terrasy' => 85, 'step6_ekskursii_muzei' => 82, 'step8_vizitnye_kartochki' => 94,
                'step1_zimoy' => 52, 'step6_gornye_lyzhi' => 48,
            ],
            'descriptions' => [
                'step5_ozera_vodopady' => 'Озеро и променад — основа образа Монтрё; замок Шильон прямо у воды усиливает «озёрный» бренд.',
                'step5_sredizemnomorskiy_vayb' => 'Швейцарская Ривьера славится мягким климатом и южным настроением набережной — ближе к «югу», чем бернские Альпы.',
                'step8_vizitnye_kartochki' => 'Шильон, набережная и вид на Альпы через озеро — классические открыточные кадры страны.',
                'step5_vinodelcheskie_terrasy' => 'Рядом террасы Лаво (ЮНЕСКО) — винный пейзаж в короткой доступности.',
            ],
        ],

        15 => [
            'name' => 'Lausanne',
            'name_ru' => 'Лозанна',
            'template' => 'lake_geneva',
            'tags' => ['Олимпийская столица', 'кафедральный собор', 'озеро', 'студенческий город'],
            'intro' => '<p><strong>Лозанна (Lausanne)</strong> — холмистый город на Женевском озере, олимпийская столица с сильной культурной сценой.</p><p>Удобен как городская база Ривьеры: собор, Ouchy, музеи; природа — озеро и выезды к Лаво и в Альпы Во.</p>',
            'scores' => [
                'step5_istoricheskie_goroda' => 88, 'step5_ozera_vodopady' => 90, 'step6_ekskursii_muzei' => 92,
                'step5_vinodelcheskie_terrasy' => 86, 'step5_sredizemnomorskiy_vayb' => 72,
                'step6_gornye_lyzhi' => 42, 'step3_solo' => 90,
            ],
            'descriptions' => [
                'step6_ekskursii_muzei' => 'Олимпийский музей, собор и плотная культурная жизнь делают Лозанну сильной «городской» точкой Ривьеры.',
                'step5_ozera_vodopady' => 'Порт Ouchy и виды на озеро — постоянный фон города; до водопадов Альп Во — отдельный выезд.',
                'step5_vinodelcheskie_terrasy' => 'До Лаво рукой подать: винные террасы — естественное продолжение лозаннской программы.',
                'step3_solo' => 'Компактный центр, метро и безопасные районы делают город удобным для самостоятельных поездок.',
            ],
        ],

        16 => [
            'name' => 'St. Moritz Region',
            'name_ru' => 'Санкт-Мориц',
            'template' => 'premium_ski',
            'tags' => ['Энгадин', 'люкс', 'озёра долины', 'зимний глянец'],
            'intro' => '<p><strong>Санкт-Мориц (St. Moritz)</strong> — легендарный люкс-курорт Энгадина с озёрами, зимним глянцем и летним хайкингом.</p><p>Бюджетный формат здесь почти не работает: регион заточен под премиум и статустный отдых.</p>',
            'scores' => [
                'step5_ozera_vodopady' => 88, 'step5_vysokogornye_alpy' => 90, 'step5_sredizemnomorskiy_vayb' => 8,
                'step6_spa_ozdorovlenie' => 85, 'step6_gastronomiya' => 94, 'step7_byudzhetnyy' => 12,
                'step7_premialnyy' => 99, 'step1_zimoy' => 96, 'step8_vizitnye_kartochki' => 93,
                'step4_obshchestvennyy_transport' => 78,
            ],
            'descriptions' => [
                'step7_premialnyy' => 'Главный deluxe-курорт страны: дворцы-отели, события и сервис уровня «видеть и быть увиденным».',
                'step7_byudzhetnyy' => 'Один из самых дорогих уголков Швейцарии — бюджетный отдых здесь практически нереалистичен.',
                'step5_ozera_vodopady' => 'Озёра Энгадина (Санкт-Мориц, Зильс и др.) дают редкий для топ-ски курорта сильный озёрный летний профиль.',
                'step1_zimoy' => 'Зима — бренд региона: лыжи, солнезии и «светский» курортный ритм.',
                'step6_gastronomiya' => 'Рестораны высокой кухни и отельный dining — гастрономия на уровне люкс-ожиданий.',
            ],
        ],

        17 => [
            'name' => 'Davos Region',
            'name_ru' => 'Давос',
            'template' => 'alpine_resort',
            'tags' => ['большой ски-регион', 'конгрессы', 'высокогорье', 'Парсенн'],
            'intro' => '<p><strong>Давос (Davos)</strong> — высокогорный курорт с огромной лыжной зоной и известной конгрессной жизнью.</p><p>Силён зимой и для активного отдыха; «сказочная деревня» и южный вайб — не его тема.</p>',
            'scores' => [
                'step1_zimoy' => 95, 'step5_vysokogornye_alpy' => 90, 'step6_gornye_lyzhi' => 95,
                'step5_ozera_vodopady' => 55, 'step5_istoricheskie_goroda' => 28, 'step6_ekskursii_muzei' => 48,
                'step7_premialnyy' => 82, 'step7_byudzhetnyy' => 40, 'step3_kompaniya_druzei' => 90,
                'step8_uedinennost' => 50, 'step5_sredizemnomorskiy_vayb' => 5,
            ],
            'descriptions' => [
                'step6_gornye_lyzhi' => 'Парсенн и связанные зоны — один из крупнейших ski-доменов Швейцарии, сильный выбор для лыжников.',
                'step1_zimoy' => 'Зима — главный смысл поездки: снег, трассы и курортная инфраструктура на высоте.',
                'step5_istoricheskie_goroda' => 'Давос — курортно-конгрессный городок XX века, не средневековый «открыточный» центр.',
                'step5_sredizemnomorskiy_vayb' => 'Суровый высокогорный климат Энгадина/Граубюндена — полной противоположностью Тичино.',
            ],
        ],

        18 => [
            'name' => 'Verbier',
            'name_ru' => 'Вербье',
            'template' => 'premium_ski',
            'tags' => ['4 Vallées', 'фрирайд', 'гламурный ски', 'Вале'],
            'intro' => '<p><strong>Вербье (Verbier)</strong> — гламурный ски-курорт системы 4 Vallées с сильным фрирайдом и ночной жизнью.</p><p>Зимой и для компании друзей почти идеален; бюджет и «тихая семья с коляской» — слабее.</p>',
            'scores' => [
                'step1_zimoy' => 97, 'step6_gornye_lyzhi' => 97, 'step5_vysokogornye_alpy' => 93,
                'step5_ozera_vodopady' => 40, 'step7_byudzhetnyy' => 20, 'step7_premialnyy' => 96,
                'step3_kompaniya_druzei' => 94, 'step3_semya_do_6' => 50, 'step5_sredizemnomorskiy_vayb' => 6,
                'step6_gastronomiya' => 88, 'step8_uedinennost' => 45, 'step4_arendovannyy_avtomobil' => 75,
            ],
            'descriptions' => [
                'step6_gornye_lyzhi' => '4 Vallées и репутация фрирайда ставят Вербье в топ ski-направлений; трассы и off-piste — главная причина ехать.',
                'step7_premialnyy' => 'Шале, апрэ-ски и высокий чек — регион явно премиальный, не «экономный семейный» курорт.',
                'step3_kompaniya_druzei' => 'Апрэ-ски, сложные склоны и тусовка делают Вербье одним из лучших выборов для компании.',
                'step5_ozera_vodopady' => 'Озёрной визитки почти нет — профиль горный и склоновый, не озёрный.',
                'step7_byudzhetnyy' => 'Жильё и подъёмники дороги; уложиться в бюджетный формат крайне сложно.',
            ],
        ],

        19 => [
            'name' => 'Andermatt',
            'name_ru' => 'Андерматт',
            'template' => 'alpine_resort',
            'tags' => ['перекрёсток перевалов', 'обновлённый курорт', 'лыжи', 'Санкт-Готард'],
            'intro' => '<p><strong>Андерматт (Andermatt)</strong> — высокогорный узел у перевалов Санкт-Готард, активно развивающийся курорт.</p><p>Хорош для лыж и хайкинга; средиземноморья и большого «старого города» здесь нет.</p>',
            'scores' => [
                'step1_zimoy' => 93, 'step5_vysokogornye_alpy' => 92, 'step6_gornye_lyzhi' => 92,
                'step5_ozera_vodopady' => 42, 'step5_istoricheskie_goroda' => 40, 'step7_premialnyy' => 86,
                'step7_byudzhetnyy' => 36, 'step8_uedinennost' => 68, 'step4_obshchestvennyy_transport' => 78,
                'step6_panoramnye_poezda' => 75, 'step5_sredizemnomorskiy_vayb' => 4,
            ],
            'descriptions' => [
                'step5_vysokogornye_alpy' => 'Расположение у перевалов даёт суровый высокогорный характер и открытые альпийские панорамы.',
                'step6_gornye_lyzhi' => 'Ски-зона заметно выросла: Андерматт стал серьёзным winter-destination, а не только транзитным посёлком.',
                'step8_uedinennost' => 'Тише Топ-бренд курортов вроде Церматта/Интерлакена — проще найти более спокойный ритм вне пиковых уик-эндов.',
                'step5_sredizemnomorskiy_vayb' => 'Центральноальпийский климат у Готарда — полная противоположность Тичино.',
            ],
        ],

        20 => [
            'name' => 'Schaffhausen',
            'name_ru' => 'Шаффхаузен',
            'template' => 'rhine_falls',
            'tags' => ['Рейнский водопад', 'старый город', 'не Альпы'],
            'intro' => '<p><strong>Шаффхаузен (Schaffhausen)</strong> — город у Рейнского водопада с живописным старым центром.</p><p>Идеален для короткого визита к водопаду и фахверкам; высокогорья и лыж здесь нет.</p>',
            'scores' => [
                'step5_ozera_vodopady' => 98, 'step5_vysokogornye_alpy' => 8, 'step5_istoricheskie_goroda' => 90,
                'step6_gornye_lyzhi' => 5, 'step8_vizitnye_kartochki' => 94, 'step2_dnya_1_2' => 98,
            ],
            'descriptions' => [
                'step5_ozera_vodopady' => 'Рейнский водопад — главный природный аттракцион севера страны; именно ради него чаще всего и едут.',
                'step5_vysokogornye_alpy' => 'Это mittelland/Рейн, не Альпы: пиков и ледников в локальном пейзаже нет.',
                'step5_istoricheskie_goroda' => 'Крепость Мунот и расписные фасады старого города дополняют водопад сильной исторической картинкой.',
                'step2_dnya_1_2' => 'Классический формат — day-trip или 1–2 дня: водопад + старый город; на неделю региона маловато.',
                'step6_gornye_lyzhi' => 'Горнолыжного курорта нет — зимой это город и водопад, не склоны.',
            ],
        ],

        21 => [
            'name' => 'Engelberg',
            'name_ru' => 'Энгельберг',
            'template' => 'alpine_resort',
            'tags' => ['Тийтлис', 'близко к Люцерну', 'лыжи+ледник', 'монастырь'],
            'intro' => '<p><strong>Энгельберг (Engelberg)</strong> — горный курорт у Тийтлиса с ледником, лыжами и удобным доступом из Люцерна.</p><p>Сильнее как активная база, чем как город культуры или «южный» отдых.</p>',
            'scores' => [
                'step1_zimoy' => 94, 'step5_vysokogornye_alpy' => 94, 'step6_gornye_lyzhi' => 94,
                'step6_panoramnye_poezda' => 90, 'step5_ozera_vodopady' => 48, 'step5_istoricheskie_goroda' => 45,
                'step4_obshchestvennyy_transport' => 90, 'step8_vizitnye_kartochki' => 88,
                'step7_byudzhetnyy' => 42, 'step5_sredizemnomorskiy_vayb' => 5,
            ],
            'descriptions' => [
                'step5_vysokogornye_alpy' => 'Тийтлис с ледником и пещерой — полноценное высокогорье в простой доступности из Центральной Швейцарии.',
                'step6_gornye_lyzhi' => 'Серьёзный ski-курорт рядом с Люцерном: для снега едут именно сюда, а не в сам город на озере.',
                'step6_panoramnye_poezda' => 'Подъёмники на Тийтлис и виды ледника — сильный панорамный продукт даже летом.',
                'step4_obshchestvennyy_transport' => 'Прямая ж/д из Люцерна делает курорт удобным без машины.',
            ],
        ],

        22 => [
            'name' => 'Grindelwald',
            'name_ru' => 'Гриндельвальд',
            'template' => 'jungfrau_valley',
            'tags' => ['Эйгер', 'First', 'лыжи+хайкинг', 'Юнгфрау'],
            'intro' => '<p><strong>Гриндельвальд (Grindelwald)</strong> — долина у северной стены Эйгера, база для First, Юнгфрауйох и зимнего спорта.</p><p>Более «курортно-отельный», чем Лаутербруннен; идеален для активного отдыха лицом к трём вершинам.</p>',
            'scores' => [
                'step5_vysokogornye_alpy' => 96, 'step5_ozera_vodopady' => 70, 'step6_gornye_lyzhi' => 92,
                'step6_peshie_progulki' => 96, 'step1_zimoy' => 90, 'step8_vizitnye_kartochki' => 96,
                'step8_uedinennost' => 38, 'step4_arendovannyy_avtomobil' => 55,
            ],
            'descriptions' => [
                'step5_vysokogornye_alpy' => 'Эйгер, Мёнх и Юнгфрау прямо над долиной — один из самых мощных высокогорных силуэтов Оберланда.',
                'step6_gornye_lyzhi' => 'Связка с Венгеном/Мюрреном даёт полноценный ski-регион; зимой Гриндельвальд сильнее «водопадных» соседей.',
                'step5_ozera_vodopady' => 'Озёр меньше, чем у Интерлакена; водопады — скорее в соседнем Лаутербруннене, отсюда удобный выезд.',
                'step6_peshie_progulki' => 'First, Bachalpsee и тропы у Эйгера — хайкинг мирового уровня.',
                'step8_vizitnye_kartochki' => 'Эйгер и Юнгфрауйох — must-see кадры швейцарских Альп.',
            ],
        ],

        23 => [
            'name' => 'Lauterbrunnen',
            'name_ru' => 'Лаутербруннен',
            'template' => 'jungfrau_valley',
            'tags' => ['долина водопадов', '72 водопада', 'ворота к Венгену/Мюррену'],
            'intro' => '<p><strong>Лаутербруннен (Lauterbrunnen)</strong> — узкая долина с десятками водопадов, ворота к Венгену и Мюррену.</p><p>Главный акцент — озёрно-водопадная вертикаль скал; сам посёлок тише и «скромнее» Гриндельвальда.</p>',
            'scores' => [
                'step5_ozera_vodopady' => 99, 'step5_vysokogornye_alpy' => 90, 'step6_gornye_lyzhi' => 70,
                'step8_uedinennost' => 58, 'step7_premialnyy' => 70, 'step8_vizitnye_kartochki' => 97,
                'step5_alpiyskie_luga' => 85, 'step1_letom' => 97, 'step4_arendovannyy_avtomobil' => 50,
            ],
            'descriptions' => [
                'step5_ozera_vodopady' => 'Штауббах, Трюммельбах и десятки других водопадов — возможно, лучший водопадный пейзаж Швейцарии.',
                'step5_vysokogornye_alpy' => 'Скальные стены и доступ к Юнгфрау-региону дают сильное высокогорье, но «лицо» долины — водопады.',
                'step6_gornye_lyzhi' => 'Лыжи в основном выше — в Венгене/Мюррене; сама долина зимой скорее база/транзит, чем ski-деревня на склоне.',
                'step8_vizitnye_kartochki' => 'Долина водопадов — узнаваемый бренд Оберланда и частый «открыточный» кадр.',
                'step8_uedinennost' => 'Тише перегруженного Гриндельвальда вне пиковых часов, но летом всё равно популярна.',
            ],
        ],

        24 => [
            'name' => 'Wengen',
            'name_ru' => 'Венген',
            'template' => 'jungfrau_valley',
            'tags' => ['car-free', 'терраса над долиной', 'лыжи Lauberhorn', 'вид на Юнгфрау'],
            'intro' => '<p><strong>Венген (Wengen)</strong> — car-free деревня на солнечной террасе над Лаутербрунненом с видом на Юнгфрау.</p><p>Сильна зимой (Lauberhorn) и как спокойная база без машин; вниз к водопадам — на поезде.</p>',
            'scores' => [
                'step6_gornye_lyzhi' => 93, 'step1_zimoy' => 92, 'step5_vysokogornye_alpy' => 94,
                'step5_ozera_vodopady' => 78, 'step4_arendovannyy_avtomobil' => 10, 'step4_obshchestvennyy_transport' => 97,
                'step8_uedinennost' => 62, 'step3_semya_ot_7' => 92, 'step7_premialnyy' => 84,
                'step5_istoricheskie_goroda' => 22,
            ],
            'descriptions' => [
                'step6_gornye_lyzhi' => 'Домашние склоны и легендарный Lauberhorn — Венген зимой сильнее «водопадной» долины внизу.',
                'step4_arendovannyy_avtomobil' => 'Деревня без машин: авто оставляют в долине, дальше только поезд — машина на месте бесполезна.',
                'step4_obshchestvennyy_transport' => 'Железная дорога — единственный и отлично отлаженный способ жить и кататься без авто.',
                'step5_ozera_vodopady' => 'Водопады видны/доступны спуском в Лаутербруннен; сами по себе Венген — скорее терраса и пики.',
                'step8_uedinennost' => 'Спокойнее хабов внизу: car-free атмосфера и террасное расположение дают больше тишины вечером.',
            ],
        ],

        25 => [
            'name' => 'Mürren',
            'name_ru' => 'Мюррен',
            'template' => 'jungfrau_valley',
            'tags' => ['car-free', 'Шилтхорн', 'вид на Эйгер', 'тихая терраса'],
            'intro' => '<p><strong>Мюррен (Mürren)</strong> — car-free деревня на противоположной от Венгена террасе с видом на Эйгер/Мёнх/Юнгфрау.</p><p>Ворота к Шилтхорну (Piz Gloria); тише и «панорамнее», с отличным хайкингом и лыжами.</p>',
            'scores' => [
                'step5_vysokogornye_alpy' => 97, 'step6_gornye_lyzhi' => 90, 'step6_panoramnye_poezda' => 94,
                'step5_ozera_vodopady' => 75, 'step4_arendovannyy_avtomobil' => 8, 'step4_obshchestvennyy_transport' => 95,
                'step8_uedinennost' => 70, 'step8_vizitnye_kartochki' => 93, 'step7_byudzhetnyy' => 34,
                'step1_letom' => 94, 'step5_alpiyskie_luga' => 92,
            ],
            'descriptions' => [
                'step5_vysokogornye_alpy' => 'Панорама трёх вершин и подъём на Шилтхорн — Мюррен ощущается как балкон высокогорья.',
                'step8_uedinennost' => 'Один из самых тихих «открыточных» пунктов Юнгфрау: нет авто и меньше суеты, чем в Гриндельвальде.',
                'step6_panoramnye_poezda' => 'Канатные дороги к Шилтхорну и виды James Bond — панорамный транспорт сильный must.',
                'step4_arendovannyy_avtomobil' => 'Как и Венген, Мюррен без машин: логистика только через подъёмники/поезда из долины.',
                'step5_alpiyskie_luga' => 'Классические луга и шале на краю обрыва — альпийская деревенская эстетика почти идеальна.',
            ],
        ],

        26 => [
            'name' => 'Locarno',
            'name_ru' => 'Локарно',
            'template' => 'ticino_mediterranean',
            'tags' => ['Лаго-Маджоре', 'кинофестиваль', 'солнце Тичино', 'Madonna del Sasso'],
            'intro' => '<p><strong>Локарно (Locarno)</strong> — солнечный город на Лаго-Маджоре с итальянским вайбом и знаменитым кинофестивалем.</p><p>Сильны озеро, пальмы и мягкая зима без серьёзных лыж; хорош весной–осенью для прогулок и южной атмосферы.</p>',
            'scores' => [
                'step5_sredizemnomorskiy_vayb' => 97, 'step5_ozera_vodopady' => 95, 'step1_zimoy' => 45,
                'step6_gornye_lyzhi' => 20, 'step6_ekskursii_muzei' => 70, 'step8_vizitnye_kartochki' => 86,
                'step5_vinodelcheskie_terrasy' => 68, 'step1_vesnoy' => 92,
            ],
            'descriptions' => [
                'step5_sredizemnomorskiy_vayb' => 'Одно из самых солнечных мест Швейцарии: пальмы, Piazza Grande и ощущение юга сильнее, чем у большинства кантонов.',
                'step5_ozera_vodopady' => 'Лаго-Маджоре и набережные — главный пейзаж; паромы к островам усиливают озёрный профиль.',
                'step1_zimoy' => 'Зима мягкая, но ski-курортом Локарно не является — это низкосезонный город у озера.',
                'step6_gornye_lyzhi' => 'Для лыж нужен выезд в другие долины; локально снежного курорта нет.',
            ],
        ],

        27 => [
            'name' => 'Ascona',
            'name_ru' => 'Аскона',
            'template' => 'ticino_mediterranean',
            'tags' => ['курорт Лаго-Маджоре', 'набережная', 'более камерный чем Локарно'],
            'intro' => '<p><strong>Аскона (Ascona)</strong> — курортный посёлок на Лаго-Маджоре рядом с Локарно, чуть более камерный и «лагунный».</p><p>Променад, кафе у воды и южный ритм; лыжи и высокогорье — не профиль.</p>',
            'scores' => [
                'step5_sredizemnomorskiy_vayb' => 98, 'step5_ozera_vodopady' => 96, 'step3_para' => 97,
                'step6_gornye_lyzhi' => 15, 'step1_zimoy' => 40, 'step7_premialnyy' => 86,
                'step8_vizitnye_kartochki' => 82, 'step5_vysokogornye_alpy' => 35,
                'step6_gastronomiya' => 94, 'step8_uedinennost' => 55,
            ],
            'descriptions' => [
                'step5_sredizemnomorskiy_vayb' => 'Набережная Асконы — квинтэссенция тичинского «почти Италия»: пальмы, лодки, медленный курортный темп.',
                'step3_para' => 'Романтический променад и ужины у воды делают Аскону одним из лучших «парных» мест Тичино.',
                'step6_gornye_lyzhi' => 'Лыжного продукта нет — зимой регион живёт мягким климатом, не склонами.',
                'step5_vysokogornye_alpy' => 'Холмы и озеро, не ледниковые четырёхтысячники; высокогорье — в других кантонах.',
                'step6_gastronomiya' => 'Рыба озера, итальянская кухня и винные бары — гастрономия здесь сильнее хайкинга.',
            ],
        ],

        28 => [
            'name' => 'Vevey',
            'name_ru' => 'Веве',
            'template' => 'lake_geneva',
            'tags' => ['гнездо Nestlé', 'набережная', 'ворота Лаво', 'Чаплин'],
            'intro' => '<p><strong>Веве (Vevey)</strong> — город на Женевском озере между Лозанной и Монтрё, у ворот виноградников Лаво.</p><p>Спокойнее Монтрё, с сильным озёрно-винным и культурным акцентом (Chaplin’s World рядом).</p>',
            'scores' => [
                'step5_vinodelcheskie_terrasy' => 94, 'step5_ozera_vodopady' => 92, 'step5_sredizemnomorskiy_vayb' => 80,
                'step6_ekskursii_muzei' => 86, 'step6_gastronomiya' => 92, 'step8_vizitnye_kartochki' => 85,
                'step6_gornye_lyzhi' => 38, 'step5_vysokogornye_alpy' => 42,
            ],
            'descriptions' => [
                'step5_vinodelcheskie_terrasy' => 'Веве — естественная база для Лаво: террасные виноградники ЮНЕСКО начинаются практически сразу.',
                'step5_ozera_vodopady' => 'Набережная и виды через озеро на Альпы — сильный озёрный кадр Ривьеры без «водопадного» Оберланда.',
                'step6_gastronomiya' => 'Вино Лаво, сыры и озёрная кухня; гастрономический акцент выше, чем ski/хайкинг.',
                'step6_ekskursii_muzei' => 'Chaplin’s World и культурная набережная дают хорошую «дождливую» программу.',
            ],
        ],

        29 => [
            'name' => 'Gstaad',
            'name_ru' => 'Гштаад',
            'template' => 'premium_ski',
            'tags' => ['гламур Бернского Оберланда', 'лыжи', 'шале', 'не Маттерхорн'],
            'intro' => '<p><strong>Гштаад (Gstaad)</strong> — гламурный курорт Бернского Оберланда с шале, лыжами и статусной публикой.</p><p>Премиум здесь почти обязателен; «дикое» одинокое высокогорье Маттерхорна — другая история.</p>',
            'scores' => [
                'step7_premialnyy' => 97, 'step7_byudzhetnyy' => 15, 'step1_zimoy' => 94,
                'step6_gornye_lyzhi' => 92, 'step5_vysokogornye_alpy' => 82, 'step5_alpiyskie_luga' => 88,
                'step5_ozera_vodopady' => 45, 'step6_gastronomiya' => 92, 'step3_para' => 95,
                'step5_sredizemnomorskiy_vayb' => 7, 'step8_vizitnye_kartochki' => 86,
            ],
            'descriptions' => [
                'step7_premialnyy' => 'Шале, бутик-отели и «светский» курортный код одежды — Гштаад стабильно в топе премиум-Швейцарии.',
                'step7_byudzhetnyy' => 'Бюджетный формат почти не совместим с ДНК курорта: цены на жильё и сервис очень высокие.',
                'step5_vysokogornye_alpy' => 'Красивые альпийские пики есть, но это не ледниковый «четырёхтысячный» вайб Церматта — скорее элегантный Оберланд.',
                'step6_gornye_lyzhi' => 'Хороший ski-регион с несколькими зонами; зима — основной драйвер поездки.',
                'step5_ozera_vodopady' => 'Озёрной визитки почти нет — пейзаж долины и склонов, не «между двумя озёрами».',
            ],
        ],

        30 => [
            'name' => 'Kandersteg',
            'name_ru' => 'Кандерштег',
            'template' => 'alpine_resort',
            'tags' => ['Оeschinensee', 'семья', 'хайкинг', 'тише Юнгфрау-хабов'],
            'intro' => '<p><strong>Кандерштег (Kandersteg)</strong> — семейный альпийский посёлок у озера Оэшинен, спокойнее туристических хабов Юнгфрау.</p><p>Силён хайкингом и озёрными панорамами; большой ski-глянец — не главная тема.</p>',
            'scores' => [
                'step5_ozera_vodopady' => 96, 'step5_vysokogornye_alpy' => 85, 'step6_peshie_progulki' => 96,
                'step6_gornye_lyzhi' => 72, 'step3_semya_do_6' => 88, 'step3_semya_ot_7' => 92,
                'step8_uedinennost' => 72, 'step8_vizitnye_kartochki' => 84, 'step7_premialnyy' => 68,
                'step7_byudzhetnyy' => 48, 'step1_letom' => 95, 'step5_sredizemnomorskiy_vayb' => 5,
            ],
            'descriptions' => [
                'step5_ozera_vodopady' => 'Оэшинензее — одно из самых красивых альпийских озёр страны; ради него часто и выбирают Кандерштег.',
                'step6_peshie_progulki' => 'Тропы вокруг Оэшинен и в округе — главный актив летнего сезона.',
                'step8_uedinennost' => 'Заметно спокойнее Интерлакена/Гриндельвальда: меньше «конвейера» туристов.',
                'step3_semya_do_6' => 'Спокойный масштаб посёлка и понятные маршруты у озера удобны семьям с малышами.',
                'step6_gornye_lyzhi' => 'Лыжи есть, но это не топ-гигант вроде Давоса/Вербье — скорее уютный средний курорт.',
            ],
        ],

        31 => [
            'name' => 'Saas-Fee Region',
            'name_ru' => 'Саас-Фе',
            'template' => 'high_alpine_ski',
            'tags' => ['ледниковая чаша', 'car-free', 'четырёхтысячники', 'Вале'],
            'intro' => '<p><strong>Саас-Фе (Saas-Fee)</strong> — car-free курорт в ледниковой чаше Вале, окружённый четырёхтысячниками.</p><p>Очень силён зимой и как высокогорье; рядом с Церматтом по духу, но чуть менее «глянцевый».</p>',
            'scores' => [
                'step5_vysokogornye_alpy' => 97, 'step1_zimoy' => 96, 'step6_gornye_lyzhi' => 96,
                'step4_arendovannyy_avtomobil' => 12, 'step4_obshchestvennyy_transport' => 93,
                'step5_sredizemnomorskiy_vayb' => 4, 'step5_ozera_vodopady' => 40, 'step7_premialnyy' => 88,
                'step7_byudzhetnyy' => 30, 'step8_vizitnye_kartochki' => 88, 'step8_uedinennost' => 58,
                'step5_vinodelcheskie_terrasy' => 15,
            ],
            'descriptions' => [
                'step5_vysokogornye_alpy' => 'Ледниковая чаша и кольцо четырёхтысячников — высокогорье здесь буквально со всех сторон.',
                'step6_gornye_lyzhi' => 'Надёжный снег и ледниковые зоны; зимой Саас-Фе конкурирует с лучшими ski-курортами Вале.',
                'step4_arendovannyy_avtomobil' => 'Деревня без авто: машина остаётся на парковке на въезде — далее электро/пешком.',
                'step5_ozera_vodopady' => 'Озёрной «открытки» мало; пейзаж — ледники и пики, не пароходные озёра.',
                'step8_uedinennost' => 'Чуть спокойнее сверхтуристичного Церматта при похожем car-free характере.',
            ],
        ],

        32 => [
            'name' => 'Crans-Montana Region',
            'name_ru' => 'Кран-Монтана',
            'template' => 'alpine_resort',
            'tags' => ['солнечное плато', 'гольф', 'лыжи', 'вид на Вале'],
            'intro' => '<p><strong>Кран-Монтана (Crans-Montana)</strong> — солнечное плато Вале с лыжами, гольфом и широкими видами на долину.</p><p>Более «курортный» и комфортный, чем суровые ледниковые деревни; хороший баланс лета и зимы.</p>',
            'scores' => [
                'step1_zimoy' => 90, 'step1_letom' => 92, 'step6_gornye_lyzhi' => 90,
                'step5_vysokogornye_alpy' => 85, 'step5_ozera_vodopady' => 72, 'step6_spa_ozdorovlenie' => 78,
                'step7_premialnyy' => 88, 'step7_byudzhetnyy' => 35, 'step5_vinodelcheskie_terrasy' => 55,
                'step5_sredizemnomorskiy_vayb' => 18, 'step8_legkiy_marshrut' => 85,
            ],
            'descriptions' => [
                'step1_letom' => 'Летом плато живёт гольфом, хайкингом и озёрами курорта — не «мёртвый» off-season ski-town.',
                'step6_gornye_lyzhi' => 'Полноценный ski-регион с хорошей инсоляцией склонов — сильный зимний продукт Вале.',
                'step5_vinodelcheskie_terrasy' => 'Виноградники долины Роны видны и доступны спуском; сам курорт выше, но винный контекст Вале рядом.',
                'step5_sredizemnomorskiy_vayb' => 'Есть солнечность и открытость плато, но это не пальмовый Тичино.',
                'step6_spa_ozdorovlenie' => 'Отельный wellness и курортный комфорт сильнее, чем у «суровых» car-free деревень.',
            ],
        ],

        33 => [
            'name' => 'Arosa',
            'name_ru' => 'Ароза',
            'template' => 'alpine_resort',
            'tags' => ['Граубюнден', 'семья', 'озёра + лыжи', 'тише Давоса'],
            'intro' => '<p><strong>Ароза (Arosa)</strong> — семейный курорт Граубюндена с озёрами и лыжной связкой Arosa–Lenzerheide.</p><p>Спокойнее и «уютнее» Давоса; хорош и зимой, и летом для хайкинга.</p>',
            'scores' => [
                'step1_zimoy' => 93, 'step6_gornye_lyzhi' => 93, 'step5_ozera_vodopady' => 82,
                'step5_vysokogornye_alpy' => 86, 'step3_semya_do_6' => 88, 'step3_semya_ot_7' => 92,
                'step8_uedinennost' => 68, 'step7_premialnyy' => 80, 'step5_sredizemnomorskiy_vayb' => 5,
                'step6_ekskursii_muzei' => 38, 'step8_vizitnye_kartochki' => 78,
            ],
            'descriptions' => [
                'step6_gornye_lyzhi' => 'Связка Arosa–Lenzerheide даёт большой современный ski-домен при более камерной атмосфере, чем Давос.',
                'step5_ozera_vodopady' => 'Курортные озёра в центре Арозы — редкий плюс для ski-городка летом.',
                'step3_semya_ot_7' => 'Семейный масштаб, понятные зоны катания и лето у озёр — сильный семейный профиль.',
                'step8_uedinennost' => 'Тише крупных конгрессных/глянцевых курортов региона.',
                'step5_sredizemnomorskiy_vayb' => 'Высокогорный Граубюнден без намёка на пальмы и южный климат.',
            ],
        ],

        34 => [
            'name' => 'Thun',
            'name_ru' => 'Тун',
            'template' => 'lake_thun_brienz',
            'tags' => ['Тунское озеро', 'замок', 'ворота Оберланда'],
            'intro' => '<p><strong>Тун (Thun)</strong> — город у Тунского озера с замком и статусом ворот Бернского Оберланда.</p><p>Сильны озеро, старый центр и логистика к Интерлакену; высокий ski — выездом.</p>',
            'scores' => [
                'step5_ozera_vodopady' => 94, 'step5_istoricheskie_goroda' => 88, 'step5_vysokogornye_alpy' => 58,
                'step6_ekskursii_muzei' => 80, 'step6_gornye_lyzhi' => 40, 'step8_prostaya_logistika' => 92,
                'step2_dnya_1_2' => 90, 'step5_sredizemnomorskiy_vayb' => 10,
            ],
            'descriptions' => [
                'step5_ozera_vodopady' => 'Тунское озеро и набережные — основа привлекательности; пароходы связывают с Интерлакеном.',
                'step5_istoricheskie_goroda' => 'Замок и старый город над рекой Аре дают сильный исторический кадр без столичного масштаба Берна.',
                'step5_vysokogornye_alpy' => 'Пики Оберланда видны и доступны, но сам Тун — озёрный город у входа в горы, не курорт у ледника.',
                'step6_gornye_lyzhi' => 'Для лыж едут дальше в долины; Тун зимой скорее база/город, чем ski-resort.',
            ],
        ],

        35 => [
            'name' => 'Brienz',
            'name_ru' => 'Бриенц',
            'template' => 'lake_thun_brienz',
            'tags' => ['Бриенцское озеро', 'резьба по дереву', 'Rothorn', 'тише Туна'],
            'intro' => '<p><strong>Бриенц (Brienz)</strong> — деревня на бирюзовом Бриенцском озере, известная резьбой по дереву и зубчаткой на Rothorn.</p><p>Спокойнее и «открыточнее» по воде, чем деловитый Тун; культура — камерная.</p>',
            'scores' => [
                'step5_ozera_vodopady' => 97, 'step5_istoricheskie_goroda' => 55, 'step6_panoramnye_poezda' => 94,
                'step5_vysokogornye_alpy' => 72, 'step6_gornye_lyzhi' => 35, 'step8_uedinennost' => 62,
                'step8_vizitnye_kartochki' => 86, 'step6_ekskursii_muzei' => 58, 'step5_alpiyskie_luga' => 80,
            ],
            'descriptions' => [
                'step5_ozera_vodopady' => 'Цвет воды Бриенцского озера — один из самых фотогеничных озёрных кадров Швейцарии.',
                'step6_panoramnye_poezda' => 'Зубчатая дорога на Brienzer Rothorn — ключевой панорамный аттракцион региона.',
                'step5_istoricheskie_goroda' => 'Это скорее курортная деревня с традиции резьбы, не крупный исторический город.',
                'step8_uedinennost' => 'Меньше городского шума и суеты, чем у Туна/Интерлакена — более тихий озёрный ритм.',
                'step6_gornye_lyzhi' => 'Лыжного курорта в посёлке нет; зима спокойная, ski — в соседних долинах.',
            ],
        ],

        36 => [
            'name' => 'Bellinzona',
            'name_ru' => 'Беллинцона',
            'template' => 'ticino_mediterranean',
            'tags' => ['три замка ЮНЕСКО', 'столица Тичино', 'меньше озера чем Лугано'],
            'intro' => '<p><strong>Беллинцона (Bellinzona)</strong> — столица Тичино с тремя замками ЮНЕСКО.</p><p>Главный акцент — история и фортификация; озера Лугано/Маджоре — рядом, но не в центре города.</p>',
            'scores' => [
                'step5_istoricheskie_goroda' => 96, 'step5_sredizemnomorskiy_vayb' => 78, 'step5_ozera_vodopady' => 55,
                'step6_ekskursii_muzei' => 90, 'step6_gornye_lyzhi' => 18, 'step1_zimoy' => 42,
                'step8_vizitnye_kartochki' => 88, 'step2_dnya_1_2' => 92, 'step5_vysokogornye_alpy' => 40,
                'step6_gastronomiya' => 88,
            ],
            'descriptions' => [
                'step5_istoricheskie_goroda' => 'Три замка ЮНЕСКО — одна из сильнейших средневековых визиток юга Швейцарии.',
                'step5_ozera_vodopady' => 'Сам город не на большом озере; озёрный вайб Тичино — скорее day-trip в Лугано/Локарно.',
                'step5_sredizemnomorskiy_vayb' => 'Итальянский язык, южный климат и ритм площади есть, но без прямой озёрной «ривьеры».',
                'step6_ekskursii_muzei' => 'Замки и музеи фортификации дают плотную экскурсионную программу на 1–2 дня.',
                'step6_gornye_lyzhi' => 'Не ski-destination: зима мягкая и городская.',
            ],
        ],

        37 => [
            'name' => 'Leukerbad',
            'name_ru' => 'Лейкербад',
            'template' => 'spa_valais',
            'tags' => ['термы', 'СПА №1', 'Вале', 'Gemmi'],
            'intro' => '<p><strong>Лейкербад (Leukerbad)</strong> — главный термальный курорт Швейцарии в чаше Вале.</p><p>СПА и оздоровление — абсолютный приоритет; горы и хайкинг дополняют, но едут прежде всего «на воды».</p>',
            'scores' => [
                'step6_spa_ozdorovlenie' => 99, 'step5_vysokogornye_alpy' => 80, 'step6_gornye_lyzhi' => 68,
                'step5_ozera_vodopady' => 40, 'step5_sredizemnomorskiy_vayb' => 8, 'step8_zashchita_ot_nepogody' => 96,
                'step3_para' => 95, 'step7_premialnyy' => 84, 'step8_vizitnye_kartochki' => 80,
                'step5_istoricheskie_goroda' => 30, 'step6_ekskursii_muzei' => 35,
            ],
            'descriptions' => [
                'step6_spa_ozdorovlenie' => 'Крупнейшие открытые/крытые термы страны: Burgerbad и др. — эталон швейцарского wellness-туризма.',
                'step8_zashchita_ot_nepogody' => 'Термы работают при дожде и холоде: регион почти идеален, если важна «страховка от непогоды».',
                'step5_vysokogornye_alpy' => 'Курорт в горной котловине с тропами Gemmi; высокогорье есть, но бренд всё же термальный.',
                'step6_gornye_lyzhi' => 'Лыжи среднего размера есть, однако они вторичны относительно терм.',
                'step5_sredizemnomorskiy_vayb' => 'Альпийская котловина Вале без пальм и озёрной ривьеры.',
            ],
        ],

        39 => [
            'name' => 'Gruyères',
            'name_ru' => 'Грюйер',
            'template' => 'heritage_town',
            'tags' => ['сыр Gruyère', 'замок', 'гастродеревня', 'короткий визит'],
            'intro' => '<p><strong>Грюйер (Gruyères)</strong> — средневековая деревня-замок и родина сыра Gruyère.</p><p>Идеальна для гастрономического и исторического day-trip; Альпы и лыжи — не главная история.</p>',
            'scores' => [
                'step5_istoricheskie_goroda' => 97, 'step6_gastronomiya' => 98, 'step5_vinodelcheskie_terrasy' => 55,
                'step5_vysokogornye_alpy' => 40, 'step5_ozera_vodopady' => 35, 'step6_gornye_lyzhi' => 30,
                'step8_vizitnye_kartochki' => 92, 'step2_dnya_1_2' => 98, 'step2_dney_5_7' => 40,
                'step5_alpiyskie_luga' => 78, 'step6_ekskursii_muzei' => 88,
            ],
            'descriptions' => [
                'step6_gastronomiya' => 'Сыр Gruyère, демонстрационная сыроварня и fondue — гастрономический must Швейцарии.',
                'step5_istoricheskie_goroda' => 'Замковый холм и цельная средневековая улица — один из самых «сказочных» heritage-кадров страны.',
                'step2_dnya_1_2' => 'Оптимально на 1 день: замок, сыр, прогулка по деревне; на неделю посёлка маловато.',
                'step5_vysokogornye_alpy' => 'Вокруг холмы Предальп; «жёсткого» ледникового высокогорья как у Маттерхорна нет.',
                'step6_gornye_lyzhi' => 'Рядом есть мелкие зоны, но Грюйер едут за сыром и замком, не за большим ski-пассом.',
            ],
        ],

        40 => [
            'name' => 'Appenzell',
            'name_ru' => 'Аппенцелль',
            'template' => 'heritage_town',
            'tags' => ['народные традиции', 'роспись домов', 'предальпы', 'Сентис рядом'],
            'intro' => '<p><strong>Аппенцелль (Appenzell)</strong> — компактный кантон с расписными домами, традициями и холмистыми предальпами.</p><p>Силён аутентичной атмосферой и лёгким хайкингом; не люкс-ски и не средиземноморье.</p>',
            'scores' => [
                'step5_istoricheskie_goroda' => 90, 'step5_alpiyskie_luga' => 92, 'step5_vysokogornye_alpy' => 55,
                'step6_peshie_progulki' => 90, 'step6_gastronomiya' => 85, 'step6_gornye_lyzhi' => 48,
                'step7_byudzhetnyy' => 58, 'step7_premialnyy' => 55, 'step8_uedinennost' => 68,
                'step5_ozera_vodopady' => 48, 'step5_sredizemnomorskiy_vayb' => 8, 'step2_dnya_3_4' => 90,
            ],
            'descriptions' => [
                'step5_alpiyskie_luga' => 'Зелёные холмы, коровы и деревенские традиции — эталон «пасторальной» Швейцарии без ледников на каждом шагу.',
                'step5_istoricheskie_goroda' => 'Расписной центр Аппенцелля — камерный, но очень характерный heritage-опыт.',
                'step6_peshie_progulki' => 'Мягкий хайкинг по холмам и выезд на Сентис — пеший акцент сильнее лыжного глянца.',
                'step7_byudzhetnyy' => 'Чуть доступнее топ-курортов Вале/Энгадина при сохранении качества; «дёшево» всё равно не будет.',
                'step5_vysokogornye_alpy' => 'Сентис даёт горный акцент, но это не четырёхтысячники Вале.',
            ],
        ],

        41 => [
            'name' => 'Stein am Rhein',
            'name_ru' => 'Штайн-ам-Райн',
            'template' => 'heritage_town',
            'tags' => ['расписные фасады', 'Рейн', 'короткий визит', 'рядом водопад'],
            'intro' => '<p><strong>Штайн-ам-Райн (Stein am Rhein)</strong> — крошечный городок с богато расписанными фасадами на Рейне.</p><p>Идеален как фотогеничный day-trip вместе с Рейнским водопадом; на долгий горный отпуск не рассчитан.</p>',
            'scores' => [
                'step5_istoricheskie_goroda' => 98, 'step5_ozera_vodopady' => 70, 'step5_vysokogornye_alpy' => 5,
                'step6_gornye_lyzhi' => 3, 'step6_ekskursii_muzei' => 85, 'step2_dnya_1_2' => 99,
                'step2_dney_5_7' => 28, 'step8_vizitnye_kartochki' => 90, 'step5_sredizemnomorskiy_vayb' => 8,
                'step7_premialnyy' => 50, 'step5_vinodelcheskie_terrasy' => 40,
            ],
            'descriptions' => [
                'step5_istoricheskie_goroda' => 'Одни из самых впечатляющих расписных фасадов Швейцарии — городок существует ради этой картинки.',
                'step2_dnya_1_2' => 'Полудня–дня достаточно на центр и набережную; часто комбинируют с Шаффхаузеном.',
                'step5_vysokogornye_alpy' => 'Равнинный север у Рейна: Альп в кадре нет совсем.',
                'step5_ozera_vodopady' => 'Сам Рейн красив; мировой водопад — рядом в Шаффхаузене, не в черте городка.',
                'step6_gornye_lyzhi' => 'Лыж нет и не будет — профиль чисто heritage/река.',
            ],
        ],

        42 => [
            'name' => 'Flims / Laax Region.',
            'name_ru' => 'Флимс / Лаакс',
            'template' => 'alpine_resort',
            'tags' => ['сноуборд/фристайл', 'Рейнское ущелье', 'лесное озеро', 'молодёжный вайб'],
            'intro' => '<p><strong>Флимс / Лаакс (Flims / Laax)</strong> — связка курортов Граубюндена с сильным сноуборд/фристайл-вайбом и летом у Caumasee и Рейнского ущелья.</p><p>Хорош для активной компании; «тихое люкс-шале Гштаада» — другая история.</p>',
            'scores' => [
                'step1_zimoy' => 94, 'step6_gornye_lyzhi' => 94, 'step3_kompaniya_druzei' => 95,
                'step5_ozera_vodopady' => 85, 'step5_vysokogornye_alpy' => 82, 'step8_uedinennost' => 48,
                'step7_premialnyy' => 78, 'step7_byudzhetnyy' => 42, 'step5_sredizemnomorskiy_vayb' => 6,
                'step6_peshie_progulki' => 90, 'step5_istoricheskie_goroda' => 30,
            ],
            'descriptions' => [
                'step6_gornye_lyzhi' => 'Laax — один из центров сноуборда и парков; зимой регион очень силён для активных райдеров.',
                'step3_kompaniya_druzei' => 'Молодёжный/дружеский вайб и апрэ-ски делают связку удобной для компаний.',
                'step5_ozera_vodopady' => 'Caumasee летом и Рейнское ущелье — сильный водный/природный бонус к ski-репутации.',
                'step5_istoricheskie_goroda' => 'Курортная застройка, не средневековый старый город.',
                'step5_vysokogornye_alpy' => 'Хорошие альпийские склоны, но силуэт мягче, чем у ледниковых курортов Вале.',
            ],
        ],

        43 => [
            'name' => 'Sion',
            'name_ru' => 'Сьон',
            'template' => 'wine_valley',
            'tags' => ['столица Вале', 'два замка', 'виноградники', 'хаб долины Роны'],
            'intro' => '<p><strong>Сьон (Sion)</strong> — столица Вале с двумя замковыми холмами посреди виноградников долины Роны.</p><p>Сильны вино, история и логистика по кантону; высокогорные лыжи — выездом в соседние долины.</p>',
            'scores' => [
                'step5_vinodelcheskie_terrasy' => 97, 'step5_istoricheskie_goroda' => 92, 'step5_vysokogornye_alpy' => 58,
                'step6_gastronomiya' => 93, 'step6_gornye_lyzhi' => 45, 'step1_osenyu' => 96,
                'step4_arendovannyy_avtomobil' => 92, 'step8_vizitnye_kartochki' => 82,
                'step5_sredizemnomorskiy_vayb' => 40, 'step5_ozera_vodopady' => 35,
            ],
            'descriptions' => [
                'step5_vinodelcheskie_terrasy' => 'Сердце винодельческого Вале: террасы вокруг города — одна из сильнейших винных картин Швейцарии.',
                'step5_istoricheskie_goroda' => 'Замки Tourbillon и Valère над старым городом — мощный historical skyline.',
                'step1_osenyu' => 'Осень — сбор винограда и золотые склоны: один из лучших сезонов именно для Сьона.',
                'step6_gornye_lyzhi' => 'Сьон — городской/винный хаб; лыжи в Verbier/Crans и др. — отдельным днём.',
                'step4_arendovannyy_avtomobil' => 'Для винных деревушек и боковых долин авто очень помогает; ОТ в центре тоже есть.',
            ],
        ],

        44 => [
            'name' => 'Fribourg',
            'name_ru' => 'Фрибур',
            'template' => 'capital_heritage',
            'tags' => ['средневековый город', 'Сарина', 'двуязычие', 'меньше туристов'],
            'intro' => '<p><strong>Фрибур (Fribourg)</strong> — хорошо сохранившийся средневековый город над рекой Сарина, менее «раскрученный», чем Берн.</p><p>Силён историей и атмосферой; озёра и лыжи — не главный мотив.</p>',
            'scores' => [
                'step5_istoricheskie_goroda' => 96, 'step5_ozera_vodopady' => 40, 'step5_vysokogornye_alpy' => 30,
                'step6_ekskursii_muzei' => 90, 'step6_gastronomiya' => 84, 'step6_gornye_lyzhi' => 25,
                'step8_uedinennost' => 58, 'step8_vizitnye_kartochki' => 78, 'step7_byudzhetnyy' => 55,
                'step5_sredizemnomorskiy_vayb' => 12, 'step2_dnya_1_2' => 94,
            ],
            'descriptions' => [
                'step5_istoricheskie_goroda' => 'Узкие улочки нижнего города и мосты через Сарину — сильный, но менее переполненный heritage, чем Берн/Люцерн.',
                'step8_uedinennost' => 'Туристов заметно меньше, чем в топ-must-see городах — проще гулять без толпы.',
                'step5_vysokogornye_alpy' => 'Город mittelland; Альпы — дальним фоном/выездом, не локальным пейзажем.',
                'step6_gornye_lyzhi' => 'Лыжного курорта нет; зима — городская и музейная.',
                'step2_dnya_1_2' => 'Оптимален как 1–2 дня между Берном и Женевским озером.',
            ],
        ],

        45 => [
            'name' => 'Adelboden',
            'name_ru' => 'Адельбоден',
            'template' => 'alpine_resort',
            'tags' => ['Бернский Оберланд', 'семья', 'лыжи', 'не Юнгфрау-хайп'],
            'intro' => '<p><strong>Адельбоден (Adelboden)</strong> — семейный курорт Бернского Оберланда с хорошими лыжами и хайкингом без толп Юнгфрау.</p><p>Сбалансированный альпийский отдых; люкс-глянец и средиземноморье — не его тема.</p>',
            'scores' => [
                'step1_zimoy' => 92, 'step6_gornye_lyzhi' => 91, 'step5_alpiyskie_luga' => 93,
                'step5_vysokogornye_alpy' => 84, 'step5_ozera_vodopady' => 55, 'step3_semya_do_6' => 86,
                'step3_semya_ot_7' => 92, 'step8_uedinennost' => 70, 'step7_premialnyy' => 72,
                'step7_byudzhetnyy' => 45, 'step8_vizitnye_kartochki' => 75, 'step5_sredizemnomorskiy_vayb' => 5,
            ],
            'descriptions' => [
                'step6_gornye_lyzhi' => 'Солидный семейный ski-регион Оберланда: меньше хайпа Юнгфрау при достойных трассах.',
                'step5_alpiyskie_luga' => 'Классические луга и шале — очень «oberland village» без ледникового драматизма Церматта.',
                'step8_uedinennost' => 'Значительно спокойнее Интерлакена/Гриндельвальда в высокий сезон.',
                'step3_semya_ot_7' => 'Семейная инфраструктура и понятные зоны катания — сильная сторона Адельбодена.',
                'step8_vizitnye_kartochki' => 'Красиво, но не входит в короткий must-see список наравне с Маттерхорном/Часовенным мостом.',
            ],
        ],

        46 => [
            'name' => 'Villars-sur-Ollon',
            'name_ru' => 'Виллар-сюр-Оллон',
            'template' => 'alpine_resort',
            'tags' => ['Во', 'вид на Дан-дю-Миди', 'семья', 'лыжи+лето'],
            'intro' => '<p><strong>Виллар-сюр-Оллон (Villars-sur-Ollon)</strong> — семейный курорт кантона Во с видами на Дан-дю-Миди и Женевское озеро вдалеке.</p><p>Удобен как спокойная альпийская база; премиум-глянец Святого Морица здесь не обязателен.</p>',
            'scores' => [
                'step1_zimoy' => 90, 'step1_letom' => 91, 'step6_gornye_lyzhi' => 88,
                'step5_vysokogornye_alpy' => 82, 'step5_ozera_vodopady' => 60, 'step3_semya_do_6' => 88,
                'step3_semya_ot_7' => 92, 'step5_alpiyskie_luga' => 90, 'step7_premialnyy' => 78,
                'step7_byudzhetnyy' => 42, 'step8_uedinennost' => 65, 'step5_sredizemnomorskiy_vayb' => 20,
                'step4_obshchestvennyy_transport' => 82, 'step8_vizitnye_kartochki' => 74,
            ],
            'descriptions' => [
                'step5_vysokogornye_alpy' => 'Панорамы Дан-дю-Миди дают ощутимый альпийский акцент без «ледниковой чаши» Вале.',
                'step6_gornye_lyzhi' => 'Хороший семейный ski с связками зоны; не Verbier по сложности/глянцу, но уверенный уровень.',
                'step3_semya_do_6' => 'Курорт ориентирован на семьи: мягкая атмосфера и удобная инфраструктура для детей.',
                'step5_sredizemnomorskiy_vayb' => 'Есть намёк на солнечность Во и виды к озеру, но это горный курорт, не Ривьера Монтрё.',
                'step8_vizitnye_kartochki' => 'Приятен и живописен, но редко первый пункт «обязательной» швейцарской программы новичков.',
            ],
        ],
    ];
}
