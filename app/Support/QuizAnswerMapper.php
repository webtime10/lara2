<?php

namespace App\Support;

use DateTimeImmutable;

class QuizAnswerMapper
{
    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $ingestResult
     * @return array<string, mixed>
     */
    public static function toAttributes(array $validated, array $ingestResult): array
    {
        $answers = is_array($validated['answers'] ?? null) ? $validated['answers'] : [];
        $catalog = is_array($answers['catalog'] ?? null) ? $answers['catalog'] : [];

        $tripDates = is_array($catalog['trip_dates'] ?? null) ? $catalog['trip_dates'] : [];
        $travelers = is_array($catalog['travelers'] ?? null) ? $catalog['travelers'] : [];
        $children = is_array($catalog['children'] ?? null) ? $catalog['children'] : [];
        $regionBlock = is_array($catalog['region'] ?? null) ? $catalog['region'] : [];
        $housing = is_array($catalog['housing'] ?? null) ? $catalog['housing'] : [];
        $comfort = is_array($catalog['comfort'] ?? null) ? $catalog['comfort'] : [];
        $entertainment = is_array($catalog['entertainment'] ?? null) ? $catalog['entertainment'] : [];
        $dining = is_array($catalog['dining'] ?? null) ? $catalog['dining'] : [];
        $carRental = is_array($catalog['car_rental'] ?? null) ? $catalog['car_rental'] : [];
        $carClass = is_array($catalog['car_class'] ?? null) ? $catalog['car_class'] : [];
        $budgetPriority = is_array($catalog['budget_priority'] ?? null) ? $catalog['budget_priority'] : [];

        $region = trim((string) ($regionBlock['region'] ?? ''));
        $travelersCount = (int) ($travelers['quantity'] ?? 0);
        $travelersCount = max(0, min(255, $travelersCount));

        $childrenCount = (int) ($children['quantity'] ?? 0);
        $childrenCount = max(0, min(255, $childrenCount));
        $totalPeople = max(0, min(65535, $travelersCount + $childrenCount));

        $childrenAges = [];
        if (! empty($children['ages']) && is_array($children['ages'])) {
            $childrenAges = array_values(array_map(static fn ($age) => (string) $age, $children['ages']));
        }

        $attributes = [
            'session_token' => isset($validated['session_token']) ? trim((string) $validated['session_token']) : null,
            'language' => (string) ($validated['language'] ?? ''),
            'trip_date_mode' => self::nullableString($tripDates['dateMode'] ?? null),
            'trip_date_from' => self::nullableString($tripDates['dateFrom'] ?? null),
            'trip_date_to' => self::nullableString($tripDates['dateTo'] ?? null),
            'trip_duration_days' => self::nullableString($tripDates['durationDays'] ?? null),
            // Считаем общее количество дней поездки и сохраняем в колонку quiz_answers.total_days.
            'total_days' => self::totalDays($tripDates),
            // Месяцы поездки на английском: June или June, July, August.
            'trip_months' => self::tripMonths($tripDates),
            'travelers_count' => $travelersCount,
            'children_count' => $childrenCount,
            // Общее количество людей: взрослые/путешественники + дети.
            'total_people' => $totalPeople,
            'children_ages' => $childrenAges !== [] ? $childrenAges : null,
            'region' => $region !== '' ? $region : null,
            'housing_type' => self::nullableString($housing['housingType'] ?? null),
            'comfort_level' => self::nullableString($comfort['comfortLevel'] ?? null),
            'entertainment_level' => self::nullableString($entertainment['entertainmentLevel'] ?? null),
            'dining_level' => self::nullableString($dining['diningLevel'] ?? null),
            'car_rental' => self::nullableString($carRental['carRental'] ?? null),
            'car_class' => self::nullableString($carClass['carClass'] ?? null),
            'budget_priority' => self::nullableString($budgetPriority['budgetPriority'] ?? null),
            'payload' => [
                'language' => $validated['language'] ?? null,
                'catalog' => $catalog,
            ],
            'ai_ok' => ! empty($ingestResult['ok']),
            'ai_model' => self::nullableString($ingestResult['model'] ?? null),
            'ai_message' => self::nullableString($ingestResult['message'] ?? null),
        ];

        $budget = is_array($ingestResult['budget'] ?? null) ? $ingestResult['budget'] : null;
        if ($budget !== null) {
            $attributes['budget_total'] = self::nullableString($budget['total'] ?? null);
            // Сырая сумма, пришедшая от Gemini по развлечениям/доп. бюджету.
            // Потом к ней прибавляется выбранное проживание: отель или апартаменты.
            $attributes['entertainment_budget_total'] = self::nullableString($budget['total'] ?? null);
            $attributes['budget_per_person'] = self::nullableString($budget['per_person'] ?? null);
            $attributes['budget_summary'] = self::nullableString($budget['summary'] ?? null);
            $attributes['budget_rows'] = is_array($budget['rows'] ?? null) ? $budget['rows'] : null;
        }

        return $attributes;
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /**
     * Возвращает итоговое количество дней поездки:
     * сначала берём готовое durationDays из WordPress, иначе считаем по dateFrom/dateTo.
     *
     * @param  array<string, mixed>  $tripDates
     */
    public static function totalDays(array $tripDates): ?int
    {
        $duration = self::nullableString($tripDates['durationDays'] ?? null);
        if ($duration !== null && preg_match('/\d+/', $duration, $matches) === 1) {
            return max(1, min(65535, (int) $matches[0]));
        }

        $from = self::parseDate($tripDates['dateFrom'] ?? null);
        $to = self::parseDate($tripDates['dateTo'] ?? null);
        if ($from === null || $to === null) {
            return null;
        }

        $days = $from->diff($to)->days;
        if ($days === false) {
            return null;
        }

        // Для калькулятора считаем календарные дни поездки включительно.
        return max(1, min(65535, $days + 1));
    }

    /**
     * Возвращает месяцы поездки на английском через запятую.
     * Если поездка попадает в несколько месяцев, сохраняем каждый месяц один раз.
     *
     * @param  array<string, mixed>  $tripDates
     */
    public static function tripMonths(array $tripDates): ?string
    {
        $from = self::parseDate($tripDates['dateFrom'] ?? null);
        $to = self::parseDate($tripDates['dateTo'] ?? null);
        if ($from === null || $to === null) {
            return null;
        }

        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        $current = $from->modify('first day of this month');
        $last = $to->modify('first day of this month');
        $months = [];

        while ($current <= $last) {
            $months[] = $current->format('F');
            $current = $current->modify('first day of next month');
        }

        return $months !== [] ? implode(', ', array_values(array_unique($months))) : null;
    }

    private static function parseDate(mixed $value): ?DateTimeImmutable
    {
        $value = self::nullableString($value);
        if ($value === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
