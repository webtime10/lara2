<?php

/**
 * Промпты ar/he → 4 температуры; ячейки → "+12° +8°|+3° -2°".
 *
 *   php database/seeders/convert_weather_temp_four.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WeatherMonthStat;
use App\Models\WeatherPromt;

$ar = <<<'TXT'
Ты помощник туристического калькулятора погоды.

Месяц: {month}
Регион: {region}
Язык ответа: арабский. Все значения в JSON пиши только на арабском.

Опиши типичную погоду для туристов в этом регионе в указанный месяц.
Заполни каждое поле непустой строкой. Пустые "" запрещены.

Верни только один JSON-объект. Без markdown и без любого текста вокруг:

{
  "average_temperature": "",
  "precipitation": "",
  "sunny_days": "",
  "season": ""
}

Правила:
- average_temperature: РОВНО четыре температуры.
  Сначала ДЕНЬ (макс, затем мин), потом НОЧЬ (макс, затем мин).
  Формат строго: "+12° +8°|+3° -2°" (пара дня, символ |, пара ночи).
  Внутри каждой пары: сначала более высокая, потом более низкая.
  Примеры: "+15° +10°|+6° +1°", зимой: "+2° -1°|-4° -9°".
  Без слов, без "...", без °C. Температуру пиши цифрами со знаком и °.
- precipitation: только одно слово: "منخفض" или "متوسط" или "مرتفع"
- sunny_days: типичное число солнечных дней в месяце, строкой, например "12"
- season: только одно слово: "ربيع" или "صيف" или "خريف" или "شتاء"
TXT;

$he = <<<'TXT'
Ты помощник туристического калькулятора погоды.

Месяц: {month}
Регион: {region}
Язык ответа: иврит. Все значения в JSON пиши только на иврите.

Опиши типичную погоду для туристов в этом регионе в указанный месяц.
Заполни каждое поле непустой строкой. Пустые "" запрещены.

Верни только один JSON-объект. Без markdown и без любого текста вокруг:

{
  "average_temperature": "",
  "precipitation": "",
  "sunny_days": "",
  "season": ""
}

Правила:
- average_temperature: РОВНО четыре температуры.
  Сначала ДЕНЬ (макс, затем мин), потом НОЧЬ (макс, затем мин).
  Формат строго: "+12° +8°|+3° -2°" (пара дня, символ |, пара ночи).
  Внутри каждой пары: сначала более высокая, потом более низкая.
  Примеры: "+15° +10°|+6° +1°", зимой: "+2° -1°|-4° -9°".
  Без слов, без "...", без °C. Температуру пиши цифрами со знаком и °.
- precipitation: только одно слово: "נמוך" или "בינוני" или "גבוה"
- sunny_days: типичное число солнечных дней в месяце, строкой, например "12"
- season: только одно слово: "אביב" или "קיץ" или "סתיו" или "חורף"
TXT;

foreach (['glavnyy_prompt_ar' => $ar, 'glavnyy_prompt_he' => $he] as $name => $content) {
    WeatherPromt::query()->updateOrCreate(
        ['name' => $name],
        ['content' => $content]
    );
    echo "prompt ok: {$name}\n";
}

$format = static function (int $d1, int $d2, int $n1, int $n2): string {
    return sprintf(
        '%+d° %+d°|%+d° %+d°',
        max($d1, $d2),
        min($d1, $d2),
        max($n1, $n2),
        min($n1, $n2)
    );
};

$normalize = static function (string $raw) use ($format): string {
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }

    if (preg_match_all('/([+-]?\d+)/u', $raw, $m) && count($m[1]) >= 4) {
        $n = array_map('intval', array_slice($m[1], 0, 4));

        return $format($n[0], $n[1], $n[2], $n[3]);
    }

    if (preg_match('/^([+-]?\d+)\s*°?\s+([+-]?\d+)\s*°?$/u', $raw, $m)) {
        $day = (int) $m[1];
        $night = (int) $m[2];
        $spread = max(2, (int) round(abs($day - $night) * 0.35));

        return $format($day, $day - $spread, $night + $spread, $night);
    }

    if (preg_match('/([+-]?\d+)\s*(?:\.\.\.|…|-|–|—|до|to)\s*([+-]?\d+)/ui', $raw, $m)) {
        $a = (int) $m[1];
        $b = (int) $m[2];
        $dMin = min($a, $b);
        $dMax = max($a, $b);

        return $format($dMax, $dMin, $dMax - 5, $dMin - 8);
    }

    if (preg_match('/^([+-]?\d+)\s*°?$/u', $raw, $m)) {
        $day = (int) $m[1];

        return $format($day + 2, $day - 2, $day - 4, $day - 8);
    }

    return $raw;
};

$updated = 0;
$skipped = 0;

WeatherMonthStat::query()
    ->whereNotNull('average_temperature')
    ->where('average_temperature', '!=', '')
    ->orderBy('id')
    ->chunkById(100, function ($rows) use ($normalize, &$updated, &$skipped) {
        foreach ($rows as $row) {
            $old = (string) $row->average_temperature;
            $new = $normalize($old);
            if ($new === '' || $new === $old) {
                $skipped++;
                continue;
            }
            $row->average_temperature = $new;
            $row->save();
            $updated++;
        }
    });

echo "converted: {$updated}, unchanged: {$skipped}\n";
echo "sample: " . WeatherMonthStat::query()
    ->where('average_temperature', 'like', '%|%')
    ->value('average_temperature') . "\n";
