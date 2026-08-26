<?php

namespace App\Support;

/**
 * Полный каталог occupancy-ячеек и выбор для карточек отелей.
 */
class HotelOccupancyCatalog
{
    /** @var list<array{age: int, label: string, short: string}> */
    public const AGE_BANDS = [
        ['age' => 2, 'label' => 'до 3 лет (маркер 2)', 'short' => '≤3'],
        ['age' => 5, 'label' => 'около 5 лет', 'short' => '~5'],
        ['age' => 8, 'label' => 'около 8 лет', 'short' => '~8'],
        ['age' => 12, 'label' => 'старше 8 (маркер 12)', 'short' => '>8'],
    ];

    /**
     * Полный каталог (adults + children ≤ 6).
     *
     * @return list<array{key: string, label: string, adults: int, children: list<int>, group: string}>
     */
    public static function all(): array
    {
        $cells = [];

        foreach ([1, 2, 3, 4] as $adults) {
            $cells[] = [
                'key' => $adults.'A',
                'label' => self::adultsLabel($adults),
                'adults' => $adults,
                'children' => [],
                'group' => 'adults',
            ];
        }

        $familyCombos = [
            [1, 1], [1, 2],
            [2, 1], [2, 2], [2, 3], [2, 4],
            [3, 1], [3, 2], [3, 3],
            [4, 1], [4, 2],
        ];

        foreach (self::AGE_BANDS as $band) {
            $age = (int) $band['age'];
            foreach ($familyCombos as [$adults, $childCount]) {
                if ($adults + $childCount > 6) {
                    continue;
                }
                $children = array_fill(0, $childCount, $age);
                $key = $adults.'A+'.$childCount.'C@'.$age;
                $cells[] = [
                    'key' => $key,
                    'label' => self::adultsLabel($adults)
                        .' + '.$childCount.' '
                        .self::childrenWord($childCount)
                        .' ('.$band['short'].', возраст '.$age.')',
                    'adults' => $adults,
                    'children' => $children,
                    'group' => 'age_'.$age,
                ];
            }
        }

        return $cells;
    }

    /**
     * Ключи по умолчанию (текущий базовый максимум на возрасте 8).
     *
     * @return list<string>
     */
    public static function defaultSelectedKeys(): array
    {
        return [
            '1A',
            '2A',
            '3A',
            '4A',
            '2A+1C@8',
            '2A+2C@8',
            '3A+1C@8',
            '3A+2C@8',
        ];
    }

    /**
     * @param  list<string>|null  $selectedKeys
     * @return list<array{key: string, label: string, adults: int, children: list<int>, group: string}>
     */
    public static function enabled(?array $selectedKeys = null): array
    {
        $selected = $selectedKeys;
        if ($selected === null || $selected === []) {
            $selected = self::defaultSelectedKeys();
        }

        $selectedMap = array_fill_keys(array_map('strval', $selected), true);
        $out = [];
        foreach (self::all() as $cell) {
            if (isset($selectedMap[$cell['key']])) {
                $out[] = $cell;
            }
        }

        return $out;
    }

    /**
     * @return array<string, list<array{key: string, label: string, adults: int, children: list<int>, group: string}>>
     */
    public static function grouped(): array
    {
        $groups = [
            'adults' => [],
        ];
        foreach (self::AGE_BANDS as $band) {
            $groups['age_'.$band['age']] = [];
        }

        foreach (self::all() as $cell) {
            $groups[$cell['group']][] = $cell;
        }

        return $groups;
    }

    private static function adultsLabel(int $adults): string
    {
        return match ($adults) {
            1 => '1 взрослый',
            2 => '2 взрослых',
            3 => '3 взрослых',
            4 => '4 взрослых',
            default => $adults.' взрослых',
        };
    }

    private static function childrenWord(int $count): string
    {
        if ($count === 1) {
            return 'ребёнок';
        }
        if ($count >= 2 && $count <= 4) {
            return 'ребёнка';
        }

        return 'детей';
    }
}
