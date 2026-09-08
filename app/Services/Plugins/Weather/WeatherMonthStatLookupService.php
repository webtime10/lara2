<?php

namespace App\Services\Plugins\Weather;

use App\Models\WeatherMonthStat;
use App\Support\WeatherCountry;
use Illuminate\Support\Facades\Log;

/**
 * Отдаёт погоду с сайта из заранее залитой weather_month_stats (без Gemini).
 */
class WeatherMonthStatLookupService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message: string, weather?: array<string, string>, model?: string, language?: string, from_db?: bool}
     */
    public function find(array $payload): array
    {
        $country = WeatherCountry::normalize((string) ($payload['country'] ?? WeatherCountry::CH));
        $language = strtolower(trim((string) ($payload['language'] ?? '')));
        $regionName = trim((string) ($payload['region_name'] ?? ''));
        $monthName = trim((string) ($payload['month_name'] ?? ''));

        $month = $this->resolveMonthNumber($payload);
        $slug = $this->resolveRegionSlug($country, $regionName);

        if ($month === null) {
            return [
                'ok' => false,
                'message' => 'Не удалось определить месяц: '.$monthName,
                'language' => $language !== '' ? $language : null,
            ];
        }

        if ($slug === null) {
            return [
                'ok' => false,
                'message' => 'Регион не найден в справочнике погоды: '.$regionName,
                'language' => $language !== '' ? $language : null,
            ];
        }

        $stat = WeatherMonthStat::query()
            ->where('country', $country)
            ->where('region_slug', $slug)
            ->where('month', $month)
            ->first();

        if (! $stat instanceof WeatherMonthStat || ! $stat->isFilled()) {
            Log::info('[plugin:weather] db miss', [
                'country' => $country,
                'slug' => $slug,
                'month' => $month,
                'region_name' => $regionName,
                'month_name' => $monthName,
            ]);

            return [
                'ok' => false,
                'message' => 'Нет готовых данных погоды для «'.$regionName.'» / «'.$monthName.'». Залейте клетку в админке Погода.',
                'language' => $language !== '' ? $language : null,
            ];
        }

        $temperature = trim((string) $stat->average_temperature);
        $tempParts = $this->splitDayNightTemperature($temperature);

        $weather = [
            'temperature' => $temperature,
            'temperature_day' => $tempParts['day'],
            'temperature_night' => $tempParts['night'],
            'precipitation' => trim((string) $stat->precipitation),
            'sunny_days' => trim((string) $stat->sunny_days),
            'season' => trim((string) $stat->season),
            'summary' => '',
        ];

        Log::info('[plugin:weather] db hit', [
            'country' => $country,
            'slug' => $slug,
            'month' => $month,
            'stat_id' => $stat->id,
        ]);

        return [
            'ok' => true,
            'message' => '',
            'weather' => $weather,
            'model' => 'weather_month_stats',
            'language' => $language !== '' ? $language : null,
            'from_db' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveMonthNumber(array $payload): ?int
    {
        $rawMonth = $payload['month'] ?? null;
        if (is_numeric($rawMonth)) {
            $n = (int) $rawMonth;
            if ($n >= 1 && $n <= 12) {
                return $n;
            }
        }

        $name = trim((string) ($payload['month_name'] ?? ''));
        if ($name === '') {
            return null;
        }

        // «1/ январь», «04 - april»
        if (preg_match('/^\s*(\d{1,2})\s*[\/.\-:]/u', $name, $m)) {
            $n = (int) $m[1];
            if ($n >= 1 && $n <= 12) {
                return $n;
            }
        }

        $lower = mb_strtolower($name, 'UTF-8');

        $map = [
            1 => ['январ', 'january', 'jan', 'يناير', 'ינואר'],
            2 => ['феврал', 'february', 'feb', 'فبراير', 'פברואר'],
            3 => ['март', 'march', 'mar', 'مارس', 'מרץ'],
            4 => ['апрел', 'april', 'apr', 'أبريل', 'ابريل', 'אפריל'],
            5 => ['май', 'may', 'مايو', 'מאי'],
            6 => ['июн', 'june', 'jun', 'يونيو', 'יוני'],
            7 => ['июл', 'july', 'jul', 'يوليو', 'יולי'],
            8 => ['август', 'august', 'aug', 'أغسطس', 'اغسطس', 'אוגוסט'],
            9 => ['сентябр', 'september', 'sep', 'sept', 'سبتمبر', 'ספטמבר'],
            10 => ['октябр', 'october', 'oct', 'أكتوبر', 'اكتوبر', 'אוקטובר'],
            11 => ['ноябр', 'november', 'nov', 'نوفمبر', 'נובמבר'],
            12 => ['декабр', 'december', 'dec', 'ديسمبر', 'דצמבר'],
        ];

        foreach ($map as $num => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($lower, mb_strtolower($needle, 'UTF-8'))) {
                    return $num;
                }
            }
        }

        return null;
    }

    private function resolveRegionSlug(string $country, string $regionName): ?string
    {
        $regionName = trim($regionName);
        if ($regionName === '') {
            return null;
        }

        $norm = $this->normalizeName($regionName);
        $regionsClass = WeatherCountry::regionsClass($country);

        foreach ($regionsClass::all() as $row) {
            $candidates = [
                $row['slug'] ?? '',
                $row['name_ru'] ?? '',
                $row['name_ar'] ?? '',
            ];
            foreach ($candidates as $candidate) {
                if ($candidate !== '' && $this->normalizeName((string) $candidate) === $norm) {
                    return (string) $row['slug'];
                }
            }
        }

        // Fallback: уже сохранённое region_name_ru в stats
        $stats = WeatherMonthStat::query()
            ->where('country', $country)
            ->select(['region_slug', 'region_name_ru'])
            ->distinct()
            ->get();

        foreach ($stats as $stat) {
            if ($this->normalizeName((string) $stat->region_name_ru) === $norm) {
                return (string) $stat->region_slug;
            }
        }

        return null;
    }

    private function normalizeName(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = str_replace(['ё'], ['е'], $value);

        return $value;
    }

    /**
     * @return array{day: string, night: string}
     */
    private function splitDayNightTemperature(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['day' => '', 'night' => ''];
        }

        if (str_contains($raw, '|')) {
            [$day, $night] = array_pad(explode('|', $raw, 2), 2, '');

            return [
                'day' => trim($day),
                'night' => trim($night),
            ];
        }

        if (preg_match_all('/([+-]?\d+)\s*°?/u', $raw, $m) && count($m[1]) >= 4) {
            $n = array_map('intval', array_slice($m[1], 0, 4));

            return [
                'day' => sprintf('%+d° %+d°', max($n[0], $n[1]), min($n[0], $n[1])),
                'night' => sprintf('%+d° %+d°', max($n[2], $n[3]), min($n[2], $n[3])),
            ];
        }

        if (preg_match('/^([+-]?\d+)\s*°?\s+([+-]?\d+)\s*°?$/u', $raw, $m)) {
            return [
                'day' => sprintf('%+d°', (int) $m[1]),
                'night' => sprintf('%+d°', (int) $m[2]),
            ];
        }

        return ['day' => $raw, 'night' => ''];
    }
}
