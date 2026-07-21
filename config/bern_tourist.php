<?php

/**
 * Туристические тематические группы → ключевые слова для сопоставления
 * с реальными category_name DataForSEO Business Listings.
 * Коды категорий НЕ задаются вручную — только матчинг по названию.
 *
 * Business Listings /locations отдаёт только страны (нет canton location_code).
 * География кантона Bern = location_coordinate + bbox-фильтры latitude/longitude
 * (не Google Maps city location_code 20129).
 */
return [
    'destination_slug' => 'bern',
    'destination_label' => 'Canton of Bern',

    /**
     * Синтетический numeric id для БД (API не отдаёт location_code кантона).
     */
    'synthetic_location_code' => 9100029,

    /**
     * Мягкое координатное окно (lat,lng,radius_km) + жёсткий bbox кантона.
     * Радиус намеренно больше города Bern (~12 km).
     */
    'location_coordinate' => '46.70,7.65,120',
    'location_name' => 'Canton of Bern (bbox + coordinate envelope)',
    'location_type' => 'canton_bbox',

    /**
     * Приблизительный bbox кантона Bern (OSM/wiki extents, rectangle).
     * Отсекает город-only сценарий; возможны пограничные пересечения с соседними кантонами.
     */
    'bbox' => [
        'min_lat' => 46.35,
        'max_lat' => 47.28,
        'min_lng' => 7.17,
        'max_lng' => 8.35,
    ],

    'category_chunk_size' => 10,

    'tourist_topic_keywords' => [
        'restaurants' => ['restaurant'],
        'cafes' => ['cafe', 'coffee_shop'],
        'bars' => ['bar', 'pub'],
        'museums' => ['museum'],
        'art galleries' => ['art_gallery'],
        'spa' => ['spa'],
        'wellness' => ['wellness_center', 'wellness', 'sauna'],
        'nightlife' => ['night_club', 'nightclub', 'disco'],
        'shopping' => ['shopping_mall', 'shopping_center', 'department_store', 'souvenir_store'],
        'hotels' => ['hotel'],
        'hostels' => ['hostel'],
        'tourist attractions' => ['tourist_attraction'],
        'amusement' => ['amusement_park', 'amusement_center'],
        'entertainment' => ['entertainment'],
        'parks' => ['park', 'national_park'],
        'zoos' => ['zoo'],
        'aquariums' => ['aquarium'],
        'ski' => ['ski', 'skiing'],
        'ski resorts' => ['ski_resort'],
        'adventure activities' => ['adventure_sports_center', 'adventure'],
        'paragliding' => ['paragliding'],
        'skydiving' => ['skydiving_center', 'skydiving'],
        'rafting' => ['rafting'],
        'climbing' => ['rock_climbing', 'climbing'],
        'tours' => ['sightseeing_tour_agency', 'tour_agency'],
        'tour operators' => ['tour_operator'],
        'travel agencies' => ['travel_agency'],
        'hiking' => ['hiking_area', 'hiking'],
        'viewpoints' => ['observation_deck', 'viewpoint'],
        'natural attractions' => ['nature_preserve', 'national_park'],
    ],
];
