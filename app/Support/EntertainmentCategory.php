<?php

namespace App\Support;

final class EntertainmentCategory
{
    public const MUSEUM = 'Museum';

    public const CINEMA = 'Cinema';

    public const ZOO = 'Zoo';

    public const AQUARIUM = 'Aquarium';

    public const AMUSEMENT_PARK = 'Amusement park';

    public const THEME_PARK = 'Theme park';

    public const WATER_PARK = 'Water park';

    public const ESCAPE_ROOM = 'Escape room';

    public const BOAT_TOUR = 'Boat tour';

    public const SKI_RESORT = 'Ski resort';

    private const MIN_REVIEWS = 10;

    private const LOCAL_MUSEUM_MIN_REVIEWS = 100;

    /** @var list<string> */
    public const ALL = [
        self::MUSEUM,
        self::CINEMA,
        self::ZOO,
        self::AQUARIUM,
        self::AMUSEMENT_PARK,
        self::THEME_PARK,
        self::WATER_PARK,
        self::ESCAPE_ROOM,
        self::BOAT_TOUR,
        self::SKI_RESORT,
    ];

    /** @var array<string, string> */
    private const MAPS_CATEGORY = [
        'museum' => self::MUSEUM,
        'cinema' => self::CINEMA,
        'movie theater' => self::CINEMA,
        'zoo' => self::ZOO,
        'aquarium' => self::AQUARIUM,
        'amusement park' => self::AMUSEMENT_PARK,
        'theme park' => self::THEME_PARK,
        'water park' => self::WATER_PARK,
        'escape room center' => self::ESCAPE_ROOM,
        'escape room' => self::ESCAPE_ROOM,
        'boat tour agency' => self::BOAT_TOUR,
        'boat tour operator' => self::BOAT_TOUR,
        'ski resort' => self::SKI_RESORT,
    ];

    /** @var list<string> */
    private const EXCLUDED_MAPS_CATEGORIES = [
        'tourist attraction',
        'historical landmark',
        'observation deck',
        'church',
        'cathedral',
        'monument',
        'memorial',
        'square',
        'park',
        'botanical garden',
        'arboretum',
    ];

    /** @var list<string> */
    private const EXCLUDED_NAME_PARTS = [
        'flower clock',
        'fountain',
        'brunnen',
        'monument',
        'memorial',
        'square',
        'platz',
        'clock',
        'denkmal',
        'cathedral',
        'church',
    ];

    /** @var list<string> */
    private const LOCAL_MUSEUM_PREFIXES = [
        'ortsmuseum',
        'heimatmuseum',
        'gemeindemuseum',
    ];

    /**
     * @param  array<string, mixed>  $item
     */
    public static function shouldIncludeItem(array $item): bool
    {
        $name = mb_strtolower(trim((string) ($item['title'] ?? '')));
        if ($name === '' || self::nameIsExcluded($name)) {
            return false;
        }

        $reviews = self::parseRating($item)['reviews'] ?? 0;
        if ($reviews < self::MIN_REVIEWS) {
            return false;
        }

        $category = self::resolveFromItem($item);
        if ($category === null) {
            return false;
        }

        if ($category === self::MUSEUM && self::isLocalMuseum($name) && $reviews < self::LOCAL_MUSEUM_MIN_REVIEWS) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public static function resolveFromItem(array $item): ?string
    {
        $title = trim((string) ($item['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $categoryFromName = self::resolveFromName($title);
        if ($categoryFromName !== null) {
            return $categoryFromName;
        }

        return self::resolveFromMapsCategory((string) ($item['category'] ?? ''));
    }

    public static function resolveFromName(string $title): ?string
    {
        if (self::nameContains($title, 'zoo')) {
            return self::ZOO;
        }

        if (self::nameContains($title, 'aquarium')) {
            return self::AQUARIUM;
        }

        if (self::nameContains($title, 'escape room') || self::nameContains($title, 'escape-room')) {
            return self::ESCAPE_ROOM;
        }

        return null;
    }

    public static function resolveFromMapsCategory(string $mapsCategory): ?string
    {
        $normalized = self::normalize($mapsCategory);

        if ($normalized === '' || in_array($normalized, self::EXCLUDED_MAPS_CATEGORIES, true)) {
            return null;
        }

        return self::MAPS_CATEGORY[$normalized] ?? null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{value: ?float, reviews: ?int}
     */
    public static function parseRating(array $item): array
    {
        if (! isset($item['rating']) || ! is_array($item['rating'])) {
            return ['value' => null, 'reviews' => null];
        }

        $value = isset($item['rating']['value']) ? (float) $item['rating']['value'] : null;
        $reviews = isset($item['rating']['votes_count']) ? (int) $item['rating']['votes_count'] : null;

        return ['value' => $value, 'reviews' => $reviews];
    }

    private static function isLocalMuseum(string $name): bool
    {
        foreach (self::LOCAL_MUSEUM_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private static function nameContains(string $title, string $needle): bool
    {
        return mb_stripos($title, $needle) !== false;
    }

    private static function nameIsExcluded(string $name): bool
    {
        foreach (self::EXCLUDED_NAME_PARTS as $part) {
            if (str_contains($name, $part)) {
                return true;
            }
        }

        return false;
    }

    private static function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
