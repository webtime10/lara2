<?php

/**
 * Ideal Region — поля категорий (оценки региона по вариантам квиза).
 *
 * Без суффикса _description → string(255) input.
 * С суффиксом _description → longText textarea.
 *
 * Порядок слотов = номер варианта в API (slot 1, 2, 3…).
 */
$steps = [
    'step1' => 'Когда вы планируете поездку?',
    'step2' => 'Какова продолжительность вашего путешествия?',
    'step3' => 'Кто входит в состав вашей группы?',
    'step4' => 'Каким транспортом вы планируете передвигаться?',
    'step5' => 'Какая природа и атмосфера вас вдохновляют?',
    'step6' => 'На каких занятиях вы хотите сделать главный акцент?',
    'step7' => 'Какой ценовой формат поездки вы рассматриваете?',
    'step8' => 'Есть ли у вас специфические пожелания или ограничения?',
];

$step_slots = [
    'step1' => ['vesnoy', 'letom', 'osenyu', 'zimoy'],
    'step2' => ['dnya_1_2', 'dnya_3_4', 'dney_5_7', 'dney_8_10', 'bolee_10_dney'],
    'step3' => ['solo', 'para', 'kompaniya_druzei', 'semya_do_6', 'semya_ot_7'],
    'step4' => ['obshchestvennyy_transport', 'arendovannyy_avtomobil', 'sochetanie'],
    'step5' => [
        'vysokogornye_alpy',
        'ozera_vodopady',
        'sredizemnomorskiy_vayb',
        'alpiyskie_luga',
        'istoricheskie_goroda',
        'vinodelcheskie_terrasy',
    ],
    'step6' => [
        'peshie_progulki',
        'gornye_lyzhi',
        'panoramnye_poezda',
        'ekskursii_muzei',
        'gastronomiya',
        'spa_ozdorovlenie',
    ],
    'step7' => ['byudzhetnyy', 'standartnyy', 'povyshennyy_komfort', 'premialnyy'],
    'step8' => [
        'legkiy_marshrut',
        'vizitnye_kartochki',
        'zashchita_ot_nepogody',
        'uedinennost',
        'prostaya_logistika',
        'net_pozhelaniy',
    ],
];

$option_titles = [
    'step1_vesnoy' => 'Весной',
    'step1_letom' => 'Летом',
    'step1_osenyu' => 'Осенью',
    'step1_zimoy' => 'Зимой',

    'step2_dnya_1_2' => '1–2 дня',
    'step2_dnya_3_4' => '3–4 дня',
    'step2_dney_5_7' => '5–7 дней',
    'step2_dney_8_10' => '8–10 дней',
    'step2_bolee_10_dney' => 'Более 10 дней',

    'step3_solo' => 'Соло-путешественник',
    'step3_para' => 'Пара',
    'step3_kompaniya_druzei' => 'Компания друзей',
    'step3_semya_do_6' => 'Семья с детьми до 6 лет',
    'step3_semya_ot_7' => 'Семья с детьми от 7 лет / подростками',

    'step4_obshchestvennyy_transport' => 'Общественный транспорт',
    'step4_arendovannyy_avtomobil' => 'Арендованный автомобиль',
    'step4_sochetanie' => 'Сочетание автомобиля и общественного транспорта',

    'step5_vysokogornye_alpy' => 'Высокогорные Альпы',
    'step5_ozera_vodopady' => 'Альпийские озера и водопады',
    'step5_sredizemnomorskiy_vayb' => 'Средиземноморский вайб',
    'step5_alpiyskie_luga' => 'Альпийские луга и деревни',
    'step5_istoricheskie_goroda' => 'Исторические старые города',
    'step5_vinodelcheskie_terrasy' => 'Винодельческие террасы и фермы',

    'step6_peshie_progulki' => 'Пешие прогулки и хайкинг',
    'step6_gornye_lyzhi' => 'Горные лыжи и зимний спорт',
    'step6_panoramnye_poezda' => 'Панорамные поезда и подъемники',
    'step6_ekskursii_muzei' => 'Музеи, галереи и замки',
    'step6_gastronomiya' => 'Гастрономический туризм',
    'step6_spa_ozdorovlenie' => 'СПА и оздоровление',

    'step7_byudzhetnyy' => 'Бюджетный',
    'step7_standartnyy' => 'Стандартный',
    'step7_povyshennyy_komfort' => 'Повышенный комфорт',
    'step7_premialnyy' => 'Премиальный',

    'step8_legkiy_marshrut' => 'Легкий маршрут',
    'step8_vizitnye_kartochki' => 'Главные визитные карточки',
    'step8_zashchita_ot_nepogody' => 'Защищенность от непогоды',
    'step8_uedinennost' => 'Уединенность',
    'step8_prostaya_logistika' => 'Простая логистика',
    'step8_net_pozhelaniy' => 'Дополнительных пожеланий нет',
];

$labels = [];
$fields = [];

foreach ($step_slots as $stepKey => $slots) {
    foreach ($slots as $slot) {
        $field = $stepKey.'_'.$slot;
        $desc = $field.'_description';
        $title = $option_titles[$field] ?? $slot;
        $labels[$field] = $title;
        $labels[$desc] = $title.' — описание';
        $fields[] = $field;
        $fields[] = $desc;
    }
}

return [
    'steps' => $steps,
    'step_slots' => $step_slots,
    'option_titles' => $option_titles,
    'labels' => $labels,
    'fields' => $fields,

    /**
     * Подсказки по правилам UI квиза (логика выбора на WP; в админке Laravel — только оценки).
     */
    'selection_rules' => [
        'step1' => ['max' => 1],
        'step2' => ['max' => 1],
        'step3' => ['max' => 1],
        'step4' => ['max' => 1],
        'step5' => ['max' => 2],
        'step6' => ['max' => 2],
        'step7' => ['max' => 1],
        'step8' => [
            'max' => 3,
            'exclusive_slot' => 'net_pozhelaniy',
        ],
    ],
];
