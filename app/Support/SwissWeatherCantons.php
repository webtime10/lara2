<?php

namespace App\Support;

/**
 * Кантоны Швейцарии для админки Weather (как в WP select: value=RU, label=AR).
 */
final class SwissWeatherCantons
{
    /**
     * @return list<array{slug: string, name_ru: string, name_ar: string}>
     */
    public static function all(): array
    {
        return [
            ['slug' => 'appenzell-ausserhoden', 'name_ru' => 'Аппенцелль-Ауссерроден', 'name_ar' => 'أبنتسل أوسر رودن'],
            ['slug' => 'appenzell-innerrhoden', 'name_ru' => 'Аппенцелль-Иннерроден', 'name_ar' => 'أبنتسل إنر رودن'],
            ['slug' => 'aargau', 'name_ru' => 'Аргау', 'name_ar' => 'أرغاو'],
            ['slug' => 'basel-land', 'name_ru' => 'Базель-Ланд', 'name_ar' => 'بازل الريف'],
            ['slug' => 'basel-stadt', 'name_ru' => 'Базель-Штадт', 'name_ar' => 'بازل المدينة'],
            ['slug' => 'bern', 'name_ru' => 'Берн', 'name_ar' => 'برن'],
            ['slug' => 'valais', 'name_ru' => 'Вале', 'name_ar' => 'فاليه'],
            ['slug' => 'vaud', 'name_ru' => 'Во', 'name_ar' => 'فو'],
            ['slug' => 'glarus', 'name_ru' => 'Гларус', 'name_ar' => 'غلاروس'],
            ['slug' => 'graubunden', 'name_ru' => 'Граубюнден', 'name_ar' => 'غراوبوندن'],
            ['slug' => 'geneva', 'name_ru' => 'Женева', 'name_ar' => 'جنيف'],
            ['slug' => 'solothurn', 'name_ru' => 'Золотурн', 'name_ar' => 'سولوتورن'],
            ['slug' => 'lucerne', 'name_ru' => 'Люцерн', 'name_ar' => 'لوتسرن'],
            ['slug' => 'neuchatel', 'name_ru' => 'Невшатель', 'name_ar' => 'نوشاتيل'],
            ['slug' => 'nidwalden', 'name_ru' => 'Нидвальден', 'name_ar' => 'نيدفالدن'],
            ['slug' => 'obwalden', 'name_ru' => 'Обвальден', 'name_ar' => 'أوبفالدن'],
            ['slug' => 'st-gallen', 'name_ru' => 'Санкт-Галлен', 'name_ar' => 'سانت غالن'],
            ['slug' => 'ticino', 'name_ru' => 'Тичино', 'name_ar' => 'تيتشينو'],
            ['slug' => 'thurgau', 'name_ru' => 'Тургау', 'name_ar' => 'تورغاو'],
            ['slug' => 'uri', 'name_ru' => 'Ури', 'name_ar' => 'أوري'],
            ['slug' => 'fribourg', 'name_ru' => 'Фрибур', 'name_ar' => 'فريبورغ'],
            ['slug' => 'zug', 'name_ru' => 'Цуг', 'name_ar' => 'تسوغ'],
            ['slug' => 'zurich', 'name_ru' => 'Цюрих', 'name_ar' => 'زيورخ'],
            ['slug' => 'schaffhausen', 'name_ru' => 'Шаффхаузен', 'name_ar' => 'شافهاوزن'],
            ['slug' => 'schwyz', 'name_ru' => 'Швиц', 'name_ar' => 'شفيتس'],
            ['slug' => 'jura', 'name_ru' => 'Юра', 'name_ar' => 'جورا'],
        ];
    }

    /** @return list<int> */
    public static function months(): array
    {
        return range(1, 12);
    }

    /** @return array<int, string> */
    public static function monthNamesRu(): array
    {
        return [
            1 => '1/ январь',
            2 => '2/ февраль',
            3 => '3/ март',
            4 => '4/ апрель',
            5 => '5/ май',
            6 => '6/ июнь',
            7 => '7/ июль',
            8 => '8/ август',
            9 => '9/ сентябрь',
            10 => '10/ октябрь',
            11 => '11/ ноябрь',
            12 => '12/ декабрь',
        ];
    }

    /**
     * @return array{slug: string, name_ru: string, name_ar: string}|null
     */
    public static function findBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        foreach (self::all() as $canton) {
            if ($canton['slug'] === $slug) {
                return $canton;
            }
        }

        return null;
    }
}
