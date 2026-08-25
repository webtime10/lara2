<?php

/**
 * Ideal Region (Япония) — поля j_category_descriptions.
 * Шаги 5–6 — японские варианты; остальные пока как у Швейцарии.
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
        'traditsionnye_goroda',
        'gory_lesa_doliny',
        'more_plyazhi_ostrova',
        'vulkany_onseny',
        'ozera_vodopady_zelen',
        'megapolisy',
    ],
    'step6' => [
        'gastronomiya',
        'onseny',
        'hramy_kultura',
        'gorodskaya_zhizn',
        'lyzhi_zimniy',
        'hiking_priroda',
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

    'step5_traditsionnye_goroda' => 'Традиционные города и старинная Япония',
    'step5_gory_lesa_doliny' => 'Горы, леса и живописные долины',
    'step5_more_plyazhi_ostrova' => 'Море, пляжи и острова',
    'step5_vulkany_onseny' => 'Вулканы и горячие источники',
    'step5_ozera_vodopady_zelen' => 'Озёра, водопады и зелёные пейзажи',
    'step5_megapolisy' => 'Мегаполисы и современная Япония',

    'step6_gastronomiya' => 'Гастрономия и местная кухня',
    'step6_onseny' => 'Онсэны и отдых в горячих источниках',
    'step6_hramy_kultura' => 'Храмы, традиционная культура и история',
    'step6_gorodskaya_zhizn' => 'Городская жизнь, шопинг и развлечения',
    'step6_lyzhi_zimniy' => 'Лыжи, сноуборд и зимний отдых',
    'step6_hiking_priroda' => 'Хайкинг и активный отдых на природе',

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

$option_hints = [
    'step5_traditsionnye_goroda' => 'Киото, Нара, Канадзава, Такаяма и т. д.',
    'step5_gory_lesa_doliny' => 'Японские Альпы, Нагано, Тохоку, Хоккайдо.',
    'step5_more_plyazhi_ostrova' => 'Окинава, острова Сэто, части Кюсю и Сикоку.',
    'step5_vulkany_onseny' => 'Кюсю, Хаконэ, Тохоку, Хоккайдо.',
    'step5_ozera_vodopady_zelen' => 'район Фудзи, Никко, Тохоку и другие природные регионы.',
    'step5_megapolisy' => 'Токио, Осака, Йокогама и т. п.',

    'step6_gastronomiya' => 'Сильный фактор выбора региона по кухне и локальным продуктам.',
    'step6_onseny' => 'Отдельная японская мотивация; хорошо разделяет регионы.',
    'step6_hramy_kultura' => 'Киото, Нара, Никко, Канадзава и т. д.',
    'step6_gorodskaya_zhizn' => 'Токио, Осака, Фукуока и другие крупные города.',
    'step6_lyzhi_zimniy' => 'Хоккайдо, Нагано, Ниигата, Тохоку.',
    'step6_hiking_priroda' => 'Японские Альпы, Хоккайдо, Якусима, Кюсю и т. д.',
];

$labels = [];
$fields = [];

foreach ($step_slots as $stepKey => $slots) {
    foreach ($slots as $slot) {
        $field = $stepKey.'_'.$slot;
        $desc = $field.'_description';
        $title = $option_titles[$field] ?? $slot;
        $hint = $option_hints[$field] ?? '';
        $labels[$field] = $hint !== '' ? ($title.' — '.$hint) : $title;
        $labels[$desc] = $title.' — описание';
        $fields[] = $field;
        $fields[] = $desc;
    }
}

return [
    'steps' => $steps,
    'step_slots' => $step_slots,
    'option_titles' => $option_titles,
    'option_hints' => $option_hints,
    'labels' => $labels,
    'fields' => $fields,
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
