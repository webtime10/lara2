<?php

namespace App\Support;

/**
 * Префектуры Японии для админки Weather (slug / RU / AR).
 */
final class JapanWeatherPrefectures
{
    /**
     * @return list<array{slug: string, name_ru: string, name_ar: string}>
     */
    public static function all(): array
    {
        return [
            ['slug' => 'hokkaido', 'name_ru' => 'Хоккайдо', 'name_ar' => 'هوكايدو'],
            ['slug' => 'aomori', 'name_ru' => 'Аомори', 'name_ar' => 'أوموري'],
            ['slug' => 'iwate', 'name_ru' => 'Ивате', 'name_ar' => 'إيواته'],
            ['slug' => 'miyagi', 'name_ru' => 'Мияги', 'name_ar' => 'مياغي'],
            ['slug' => 'akita', 'name_ru' => 'Акита', 'name_ar' => 'أكيطا'],
            ['slug' => 'yamagata', 'name_ru' => 'Ямагата', 'name_ar' => 'ياماغاتا'],
            ['slug' => 'fukushima', 'name_ru' => 'Фукусима', 'name_ar' => 'فوكوشيما'],
            ['slug' => 'ibaraki', 'name_ru' => 'Ибараки', 'name_ar' => 'إيباراكي'],
            ['slug' => 'tochigi', 'name_ru' => 'Тотиги', 'name_ar' => 'توتشيغي'],
            ['slug' => 'gunma', 'name_ru' => 'Гумма', 'name_ar' => 'غونما'],
            ['slug' => 'saitama', 'name_ru' => 'Сайтама', 'name_ar' => 'سايتاما'],
            ['slug' => 'chiba', 'name_ru' => 'Тиба', 'name_ar' => 'تشيبا'],
            ['slug' => 'tokyo', 'name_ru' => 'Токио', 'name_ar' => 'طوكيو'],
            ['slug' => 'kanagawa', 'name_ru' => 'Канагава', 'name_ar' => 'كاناغاوا'],
            ['slug' => 'niigata', 'name_ru' => 'Ниигата', 'name_ar' => 'نييغاتا'],
            ['slug' => 'toyama', 'name_ru' => 'Тояма', 'name_ar' => 'توياما'],
            ['slug' => 'ishikawa', 'name_ru' => 'Исикава', 'name_ar' => 'إيشيكاوا'],
            ['slug' => 'fukui', 'name_ru' => 'Фукуи', 'name_ar' => 'فوكوي'],
            ['slug' => 'yamanashi', 'name_ru' => 'Яманаси', 'name_ar' => 'ياماناشي'],
            ['slug' => 'nagano', 'name_ru' => 'Нагано', 'name_ar' => 'ناغانو'],
            ['slug' => 'gifu', 'name_ru' => 'Гифу', 'name_ar' => 'غيفو'],
            ['slug' => 'shizuoka', 'name_ru' => 'Сидзуока', 'name_ar' => 'شيزوكا'],
            ['slug' => 'aichi', 'name_ru' => 'Айти', 'name_ar' => 'آيتشي'],
            ['slug' => 'mie', 'name_ru' => 'Миэ', 'name_ar' => 'ميه'],
            ['slug' => 'shiga', 'name_ru' => 'Сига', 'name_ar' => 'شيغا'],
            ['slug' => 'kyoto', 'name_ru' => 'Киото', 'name_ar' => 'كيوتو'],
            ['slug' => 'osaka', 'name_ru' => 'Осака', 'name_ar' => 'أوساكا'],
            ['slug' => 'hyogo', 'name_ru' => 'Хёго', 'name_ar' => 'هيوغو'],
            ['slug' => 'nara', 'name_ru' => 'Нара', 'name_ar' => 'نارا'],
            ['slug' => 'wakayama', 'name_ru' => 'Вакаяма', 'name_ar' => 'واكاياما'],
            ['slug' => 'tottori', 'name_ru' => 'Тоттори', 'name_ar' => 'توتوري'],
            ['slug' => 'shimane', 'name_ru' => 'Симане', 'name_ar' => 'شيمانه'],
            ['slug' => 'okayama', 'name_ru' => 'Окаяма', 'name_ar' => 'أوكاياما'],
            ['slug' => 'hiroshima', 'name_ru' => 'Хиросима', 'name_ar' => 'هيروشيما'],
            ['slug' => 'yamaguchi', 'name_ru' => 'Ямагути', 'name_ar' => 'ياماغوتشي'],
            ['slug' => 'tokushima', 'name_ru' => 'Токусима', 'name_ar' => 'توكوشيما'],
            ['slug' => 'kagawa', 'name_ru' => 'Кагава', 'name_ar' => 'كاغاوا'],
            ['slug' => 'ehime', 'name_ru' => 'Эхимэ', 'name_ar' => 'إهيمه'],
            ['slug' => 'kochi', 'name_ru' => 'Коти', 'name_ar' => 'كوتشي'],
            ['slug' => 'fukuoka', 'name_ru' => 'Фукуока', 'name_ar' => 'فوكوكا'],
            ['slug' => 'saga', 'name_ru' => 'Сага', 'name_ar' => 'ساغا'],
            ['slug' => 'nagasaki', 'name_ru' => 'Нагасаки', 'name_ar' => 'ناغاساكي'],
            ['slug' => 'kumamoto', 'name_ru' => 'Кумамото', 'name_ar' => 'كوماموتو'],
            ['slug' => 'oita', 'name_ru' => 'Оита', 'name_ar' => 'أويتا'],
            ['slug' => 'miyazaki', 'name_ru' => 'Миядзаки', 'name_ar' => 'ميازاكي'],
            ['slug' => 'kagoshima', 'name_ru' => 'Кагосима', 'name_ar' => 'كاغوشيما'],
            ['slug' => 'okinawa', 'name_ru' => 'Окинава', 'name_ar' => 'أوكيناوا'],
        ];
    }

    /** @return list<int> */
    public static function months(): array
    {
        return SwissWeatherCantons::months();
    }

    /** @return array<int, string> */
    public static function monthNamesRu(): array
    {
        return SwissWeatherCantons::monthNamesRu();
    }

    /**
     * @return array{slug: string, name_ru: string, name_ar: string}|null
     */
    public static function findBySlug(string $slug): ?array
    {
        $slug = strtolower(trim($slug));
        foreach (self::all() as $row) {
            if ($row['slug'] === $slug) {
                return $row;
            }
        }

        return null;
    }
}
