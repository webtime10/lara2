<?php

namespace App\Support;

final class EntertainmentBrand
{
    private const MIN_PREFIX_WORDS = 2;

    /**
     * @param  list<string>  $namesInCategory
     */
    public static function resolve(string $name, array $namesInCategory): string
    {
        $name = trim($name);
        $words = preg_split('/\s+/u', $name) ?: [];

        if (count($words) <= self::MIN_PREFIX_WORDS) {
            return $name;
        }

        for ($len = count($words) - 1; $len >= self::MIN_PREFIX_WORDS; $len--) {
            $prefix = implode(' ', array_slice($words, 0, $len));
            if (self::prefixMatchesAtLeast($prefix, $namesInCategory, 2)) {
                return $prefix;
            }
        }

        return $name;
    }

    /**
     * @param  list<array{name: string, category: string}>  $items
     * @return array{
     *     total_objects: int,
     *     total_brands: int,
     *     categories: array<string, array{objects: int, brands: int}>
     * }
     */
    public static function regionSummary(array $items): array
    {
        /** @var array<string, list<string>> $namesByCategory */
        $namesByCategory = [];

        foreach ($items as $item) {
            $category = $item['category'] ?? '';
            if ($category === '') {
                continue;
            }

            $namesByCategory[$category][] = $item['name'];
        }

        $allBrandKeys = [];
        $categories = [];

        foreach (EntertainmentCategory::ALL as $category) {
            $names = $namesByCategory[$category] ?? [];
            $brandKeys = [];

            foreach ($names as $name) {
                $brand = self::resolve($name, $names);
                $brandKey = mb_strtolower($category.'|'.$brand);
                $brandKeys[$brandKey] = true;
                $allBrandKeys[$brandKey] = true;
            }

            $categories[$category] = [
                'objects' => count($names),
                'brands' => count($brandKeys),
            ];
        }

        return [
            'total_objects' => count($items),
            'total_brands' => count($allBrandKeys),
            'categories' => $categories,
        ];
    }

    /**
     * @param  list<string>  $names
     */
    private static function prefixMatchesAtLeast(string $prefix, array $names, int $minimum): bool
    {
        $prefixLower = mb_strtolower($prefix);
        $matches = 0;

        foreach ($names as $name) {
            if (str_starts_with(mb_strtolower(trim($name)), $prefixLower)) {
                $matches++;
                if ($matches >= $minimum) {
                    return true;
                }
            }
        }

        return false;
    }
}
