<?php

namespace App\Support;

final class ApartmentPriceLevel
{
    /**
     * 1 = простой, 2 = средний, 3 = высокий (по цене внутри одного кантона).
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function assign(array $items, string $priceKey = 'price'): array
    {
        if ($items === []) {
            return [];
        }

        usort($items, static fn (array $a, array $b): int => ((float) $a[$priceKey]) <=> ((float) $b[$priceKey]));

        $count = count($items);
        $third = (int) ceil($count / 3);

        foreach ($items as $index => &$item) {
            if ($count === 1) {
                $item['level'] = 2;
                continue;
            }

            if ($index < $third) {
                $item['level'] = 1;
            } elseif ($index < $third * 2) {
                $item['level'] = 2;
            } else {
                $item['level'] = 3;
            }
        }
        unset($item);

        return $items;
    }
}
