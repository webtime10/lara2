<?php

namespace App\Support;

/**
 * Префектуры Японии для админки Weather (slug / RU / HE).
 */
final class JapanWeatherPrefectures
{
    /**
     * @return list<array{slug: string, name_ru: string, name_ar: string, name_he: string}>
     */
    public static function all(): array
    {
        return [
            ['slug' => 'hokkaido', 'name_ru' => 'Хоккайдо', 'name_ar' => 'هوكايدو', 'name_he' => 'הוקאידו'],
            ['slug' => 'aomori', 'name_ru' => 'Аомори', 'name_ar' => 'أوموري', 'name_he' => 'אאומורי'],
            ['slug' => 'iwate', 'name_ru' => 'Ивате', 'name_ar' => 'إيواته', 'name_he' => 'איווטה'],
            ['slug' => 'miyagi', 'name_ru' => 'Мияги', 'name_ar' => 'مياغي', 'name_he' => 'מיאגי'],
            ['slug' => 'akita', 'name_ru' => 'Акита', 'name_ar' => 'أكيطا', 'name_he' => 'אקיטה'],
            ['slug' => 'yamagata', 'name_ru' => 'Ямагата', 'name_ar' => 'ياماغاتا', 'name_he' => 'יאמאגאטה'],
            ['slug' => 'fukushima', 'name_ru' => 'Фукусима', 'name_ar' => 'فوكوشيما', 'name_he' => 'פוקושימה'],
            ['slug' => 'ibaraki', 'name_ru' => 'Ибараки', 'name_ar' => 'إيباراكي', 'name_he' => 'איבראקי'],
            ['slug' => 'tochigi', 'name_ru' => 'Тотиги', 'name_ar' => 'توتشيغي', 'name_he' => "טוצ'יגי"],
            ['slug' => 'gunma', 'name_ru' => 'Гумма', 'name_ar' => 'غونما', 'name_he' => 'גונמה'],
            ['slug' => 'saitama', 'name_ru' => 'Сайтама', 'name_ar' => 'سايتاما', 'name_he' => 'סאיטאמה'],
            ['slug' => 'chiba', 'name_ru' => 'Тиба', 'name_ar' => 'تشيبا', 'name_he' => "צ'יבה"],
            ['slug' => 'tokyo', 'name_ru' => 'Токио', 'name_ar' => 'طوكيو', 'name_he' => 'טוקיו'],
            ['slug' => 'kanagawa', 'name_ru' => 'Канагава', 'name_ar' => 'كاناغاوا', 'name_he' => 'קאנאגאווה'],
            ['slug' => 'niigata', 'name_ru' => 'Ниигата', 'name_ar' => 'نييغاتا', 'name_he' => 'ניאיגאטה'],
            ['slug' => 'toyama', 'name_ru' => 'Тояма', 'name_ar' => 'توياما', 'name_he' => 'טויאמה'],
            ['slug' => 'ishikawa', 'name_ru' => 'Исикава', 'name_ar' => 'إيشيكاوا', 'name_he' => 'אישיקאווה'],
            ['slug' => 'fukui', 'name_ru' => 'Фукуи', 'name_ar' => 'فوكوي', 'name_he' => 'פוקוי'],
            ['slug' => 'yamanashi', 'name_ru' => 'Яманаси', 'name_ar' => 'ياماناشي', 'name_he' => 'יאמאנאשי'],
            ['slug' => 'nagano', 'name_ru' => 'Нагано', 'name_ar' => 'ناغانو', 'name_he' => 'נאגאנו'],
            ['slug' => 'gifu', 'name_ru' => 'Гифу', 'name_ar' => 'غيفو', 'name_he' => 'גיפו'],
            ['slug' => 'shizuoka', 'name_ru' => 'Сидзуока', 'name_ar' => 'شيزوكا', 'name_he' => 'שיזואוקה'],
            ['slug' => 'aichi', 'name_ru' => 'Айти', 'name_ar' => 'آيتشي', 'name_he' => "אייצ'י"],
            ['slug' => 'mie', 'name_ru' => 'Миэ', 'name_ar' => 'ميه', 'name_he' => 'מיאה'],
            ['slug' => 'shiga', 'name_ru' => 'Сига', 'name_ar' => 'شيغا', 'name_he' => 'שיגה'],
            ['slug' => 'kyoto', 'name_ru' => 'Киото', 'name_ar' => 'كيوتو', 'name_he' => 'קיוטו'],
            ['slug' => 'osaka', 'name_ru' => 'Осака', 'name_ar' => 'أوساكا', 'name_he' => 'אוסקה'],
            ['slug' => 'hyogo', 'name_ru' => 'Хёго', 'name_ar' => 'هيوغو', 'name_he' => 'היוגו'],
            ['slug' => 'nara', 'name_ru' => 'Нара', 'name_ar' => 'نارا', 'name_he' => 'נארה'],
            ['slug' => 'wakayama', 'name_ru' => 'Вакаяма', 'name_ar' => 'واكاياما', 'name_he' => 'וואקאיאמה'],
            ['slug' => 'tottori', 'name_ru' => 'Тоттори', 'name_ar' => 'توتوري', 'name_he' => 'טוטורי'],
            ['slug' => 'shimane', 'name_ru' => 'Симане', 'name_ar' => 'شيمانه', 'name_he' => 'שימאנה'],
            ['slug' => 'okayama', 'name_ru' => 'Окаяма', 'name_ar' => 'أوكاياما', 'name_he' => 'אוקאיאמה'],
            ['slug' => 'hiroshima', 'name_ru' => 'Хиросима', 'name_ar' => 'هيروشيما', 'name_he' => 'הירושימה'],
            ['slug' => 'yamaguchi', 'name_ru' => 'Ямагути', 'name_ar' => 'ياماغوتشي', 'name_he' => "יאמאגוצ'י"],
            ['slug' => 'tokushima', 'name_ru' => 'Токусима', 'name_ar' => 'توكوشيما', 'name_he' => 'טוקושימה'],
            ['slug' => 'kagawa', 'name_ru' => 'Кагава', 'name_ar' => 'كاغاوا', 'name_he' => 'קאגאווה'],
            ['slug' => 'ehime', 'name_ru' => 'Эхимэ', 'name_ar' => 'إهيمه', 'name_he' => 'אהימה'],
            ['slug' => 'kochi', 'name_ru' => 'Коти', 'name_ar' => 'كوتشي', 'name_he' => "קוצ'י"],
            ['slug' => 'fukuoka', 'name_ru' => 'Фукуока', 'name_ar' => 'فوكوكا', 'name_he' => 'פוקואוקה'],
            ['slug' => 'saga', 'name_ru' => 'Сага', 'name_ar' => 'ساغا', 'name_he' => 'סאגה'],
            ['slug' => 'nagasaki', 'name_ru' => 'Нагасаки', 'name_ar' => 'ناغاساكي', 'name_he' => 'נאגאסאקי'],
            ['slug' => 'kumamoto', 'name_ru' => 'Кумамото', 'name_ar' => 'كوماموتو', 'name_he' => 'קומאמוטו'],
            ['slug' => 'oita', 'name_ru' => 'Оита', 'name_ar' => 'أويتا', 'name_he' => 'אואיטה'],
            ['slug' => 'miyazaki', 'name_ru' => 'Миядзаки', 'name_ar' => 'ميازاكي', 'name_he' => 'מיאזאקי'],
            ['slug' => 'kagoshima', 'name_ru' => 'Кагосима', 'name_ar' => 'كاغوشيما', 'name_he' => 'קאגושימה'],
            ['slug' => 'okinawa', 'name_ru' => 'Окинава', 'name_ar' => 'أوكيناوا', 'name_he' => 'אוקינאווה'],
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
     * @return array{slug: string, name_ru: string, name_ar: string, name_he: string}|null
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
