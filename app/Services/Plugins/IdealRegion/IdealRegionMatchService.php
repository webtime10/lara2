<?php

namespace App\Services\Plugins\IdealRegion;

use App\Models\Category;
use App\Models\JCategory;
use App\Models\Language;
use App\Models\Manufacturer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class IdealRegionMatchService
{
    private const JAPAN_MANUFACTURER_ID = 2;

    private const SWISS_CONTENT_LANG = 'ar';

    private const JAPAN_CONTENT_LANG = 'he';

    /**
     * @return array<string, list<string>>
     */
    public function stepSlots(?Manufacturer $manufacturer = null): array
    {
        $configKey = $this->isJapanManufacturer($manufacturer)
            ? 'japan_ideal_region_category_fields.step_slots'
            : 'ideal_region_category_fields.step_slots';

        $slots = (array) config($configKey, []);

        return array_filter($slots, fn ($fields) => is_array($fields) && $fields !== []);
    }

    /**
     * Оценки категории по шагам: step_1 → { "1": 10, "2": 3, … }.
     *
     * @param  Model  $description  CategoryDescription|JCategoryDescription
     * @return array<string, array<string, int|null>>
     */
    public function numberedStepsForDescription(Model $description, ?Manufacturer $manufacturer = null): array
    {
        $out = [];

        foreach ($this->stepSlots($manufacturer) as $stepKey => $fields) {
            $stepNum = (int) str_replace('step', '', (string) $stepKey);
            if ($stepNum <= 0) {
                continue;
            }

            $stepData = [];
            foreach (array_values($fields) as $index => $field) {
                $column = 'step'.$stepNum.'_'.$field;
                $raw = $description->{$column} ?? null;
                $stepData[(string) ($index + 1)] = $this->toScore($raw);
            }

            $out['step_'.$stepNum] = $stepData;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function match(array $payload): array
    {
        $languageCode = $this->normalizeLanguageCode((string) ($payload['language'] ?? ''));
        $payloadManufacturerId = isset($payload['manufacturer_id']) ? (int) $payload['manufacturer_id'] : 0;
        $manufacturer = $this->resolveManufacturer($payloadManufacturerId > 0 ? $payloadManufacturerId : null);
        $manufacturerId = $manufacturer?->id;

        if (! $manufacturerId || ! $manufacturer) {
            return [
                'language' => $languageCode !== '' ? $languageCode : null,
                'manufacturer_id' => null,
                'manufacturer_name' => null,
                'user_choices' => [],
                'matched_regions' => [],
                'matched_names' => [],
                'best_match' => null,
                'error' => 'Manufacturer not found.',
            ];
        }

        $isJapan = $this->isJapanManufacturer($manufacturer);
        $language = $this->resolveContentLanguage($languageCode, $isJapan);
        $imageLanguage = $this->resolveImageLanguage($languageCode, $language);

        $userChoices = $this->parseUserChoices($payload['answers']['catalog'] ?? []);

        $regions = $isJapan
            ? $this->matchJapanRegions($language, $imageLanguage, $userChoices, $manufacturer)
            : $this->matchSwissRegions($language, $imageLanguage, $userChoices, $manufacturer);

        // Step 1 доминирует: сначала сезон (шаг 1), затем уточнение по шагам 2–8.
        $sorted = $regions
            ->filter(fn ($region) => ($region['step1_score'] ?? 0) > 0 || ($region['match_score'] ?? 0) > 0)
            ->sort(function (array $a, array $b) {
                $step1 = ($b['step1_score'] ?? 0) <=> ($a['step1_score'] ?? 0);
                if ($step1 !== 0) {
                    return $step1;
                }

                $rest = ($b['rest_score'] ?? 0) <=> ($a['rest_score'] ?? 0);
                if ($rest !== 0) {
                    return $rest;
                }

                return ($b['match_score'] ?? 0) <=> ($a['match_score'] ?? 0);
            })
            ->values();

        $topRegions = $this->pickTopByDominantStep1($sorted, 3);

        return [
            'language' => $language?->code ?? $languageCode,
            'manufacturer_id' => $manufacturerId,
            'manufacturer_name' => $manufacturer->name,
            'user_choices' => $userChoices,
            'ranking' => 'step1_dominant',
            'top_regions' => $topRegions,
            'top_names' => array_values(array_map(fn ($r) => $r['name'], $topRegions)),
            'matched_regions' => $topRegions,
            'matched_names' => array_values(array_map(fn ($r) => $r['name'], $topRegions)),
            'regions' => $sorted->all(),
            'best_match' => $topRegions[0] ?? null,
        ];
    }

    /**
     * @param  array<string, list<int>>  $userChoices
     * @return Collection<int, array<string, mixed>>
     */
    private function matchSwissRegions(
        ?Language $language,
        ?Language $imageLanguage,
        array $userChoices,
        Manufacturer $manufacturer
    ): Collection {
        $langIds = array_values(array_unique(array_filter([
            $language?->id,
            $imageLanguage?->id,
        ])));

        $categories = Category::query()
            ->with(['descriptions' => fn ($q) => $langIds !== []
                ? $q->whereIn('language_id', $langIds)
                : $q])
            ->where('manufacturer_id', $manufacturer->id)
            ->where('status', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $categories->map(function (Category $category) use ($language, $imageLanguage, $userChoices, $manufacturer) {
            $description = $language
                ? $category->descriptions->firstWhere('language_id', $language->id)
                : $category->descriptions->first();

            if (! $description || trim((string) ($description->name ?? '')) === '') {
                return null;
            }

            $imageDesc = $imageLanguage
                ? $category->descriptions->firstWhere('language_id', $imageLanguage->id)
                : null;

            $image = (string) (($imageDesc->image ?? null)
                ?: ($description->image ?? null)
                ?: ($category->image ?? ''));

            return $this->buildRegionResult(
                (int) $category->id,
                $description,
                $image,
                $userChoices,
                $manufacturer
            );
        })->filter()->values();
    }

    /**
     * @param  array<string, list<int>>  $userChoices
     * @return Collection<int, array<string, mixed>>
     */
    private function matchJapanRegions(
        ?Language $language,
        ?Language $imageLanguage,
        array $userChoices,
        Manufacturer $manufacturer
    ): Collection {
        $langIds = array_values(array_unique(array_filter([
            $language?->id,
            $imageLanguage?->id,
        ])));

        $categories = JCategory::query()
            ->with(['descriptions' => fn ($q) => $langIds !== []
                ? $q->whereIn('language_id', $langIds)
                : $q])
            ->where('manufacturer_id', $manufacturer->id)
            ->where('status', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $categories->map(function (JCategory $category) use ($language, $imageLanguage, $userChoices, $manufacturer) {
            $description = $language
                ? $category->descriptions->firstWhere('language_id', $language->id)
                : $category->descriptions->first();

            if (! $description || trim((string) ($description->name ?? '')) === '') {
                return null;
            }

            $imageDesc = $imageLanguage
                ? $category->descriptions->firstWhere('language_id', $imageLanguage->id)
                : null;

            $image = (string) (($imageDesc->image ?? null)
                ?: ($description->image ?? null)
                ?: ($category->image ?? ''));

            return $this->buildRegionResult(
                (int) $category->id,
                $description,
                $image,
                $userChoices,
                $manufacturer
            );
        })->filter()->values();
    }

    /**
     * @param  array<string, list<int>>  $userChoices
     * @return array<string, mixed>
     */
    private function buildRegionResult(
        int $categoryId,
        Model $description,
        string $imageRaw,
        array $userChoices,
        Manufacturer $manufacturer
    ): array {
        $steps = $this->numberedStepsForDescription($description, $manufacturer);
        $criteriaScores = $this->criteriaScoresForChoices($steps, $userChoices);
        $step1Score = (int) ($criteriaScores['step_1'] ?? 0);
        $restScore = $this->calculateRestScore($criteriaScores);
        $matchScore = $this->calculateWeightedScore($step1Score, $restScore);

        return [
            'category_id' => $categoryId,
            'name' => (string) ($description->name ?? ''),
            'slug' => (string) ($description->slug ?? ''),
            'image' => $this->relativeImagePath($imageRaw),
            'description' => $this->plainDescription($description->description ?? null),
            'description_html' => $this->safeHtmlDescription($description->description ?? null),
            'step1_score' => $step1Score,
            'rest_score' => $restScore,
            'match_score' => $matchScore,
            'criteria_scores' => $criteriaScores,
            'steps' => $steps,
        ];
    }

    /**
     * Берём топ-N: сначала среди регионов с максимальным step1,
     * если мало — расширяем на step1-1, и т.д.
     *
     * @param  Collection<int, array<string, mixed>>  $sorted
     * @return list<array<string, mixed>>
     */
    private function pickTopByDominantStep1($sorted, int $limit = 2): array
    {
        if ($sorted->isEmpty() || $limit <= 0) {
            return [];
        }

        $picked = [];
        $minStep1 = (int) ($sorted->first()['step1_score'] ?? 0);

        while (count($picked) < $limit && $minStep1 >= 0) {
            $tier = $sorted
                ->filter(fn ($region) => (int) ($region['step1_score'] ?? 0) === $minStep1)
                ->sortByDesc('rest_score')
                ->values();

            foreach ($tier as $region) {
                $id = (int) ($region['category_id'] ?? 0);
                if ($id > 0 && isset($picked[$id])) {
                    continue;
                }
                $key = $id > 0 ? $id : count($picked);
                $picked[$key] = $region;
                if (count($picked) >= $limit) {
                    break 2;
                }
            }

            $minStep1--;
        }

        return array_values($picked);
    }

    private function resolveManufacturer(?int $payloadId = null): ?Manufacturer
    {
        if ($payloadId !== null && $payloadId > 0) {
            $fromPayload = Manufacturer::query()->find($payloadId);
            if ($fromPayload) {
                return $fromPayload;
            }
        }

        $configuredId = config('services.plugins.ideal_region.manufacturer_id');
        if (filled($configuredId) && (int) $configuredId > 0) {
            return Manufacturer::query()->find((int) $configuredId);
        }

        $configuredName = trim((string) config('services.plugins.ideal_region.manufacturer_name', 'Швейцария'));
        $candidates = array_unique(array_filter([
            $configuredName,
            'Швейцария',
            'Switzerland',
            'Schweiz',
            'Suisse',
        ]));

        foreach ($candidates as $name) {
            $manufacturer = Manufacturer::query()->where('name', $name)->first();
            if ($manufacturer) {
                return $manufacturer;
            }
        }

        return Manufacturer::query()
            ->where('name', 'like', '%Швейцар%')
            ->orWhere('name', 'like', '%Switzerland%')
            ->orWhere('name', 'like', '%Schweiz%')
            ->orderBy('id')
            ->first();
    }

    private function isJapanManufacturer(?Manufacturer $manufacturer): bool
    {
        if (! $manufacturer) {
            return false;
        }

        if ((int) $manufacturer->id === self::JAPAN_MANUFACTURER_ID) {
            return true;
        }

        $name = mb_strtolower(trim((string) $manufacturer->name));

        return str_contains($name, 'япон')
            || str_contains($name, 'japan');
    }

    private function normalizeLanguageCode(string $code): string
    {
        $code = strtolower(trim($code));
        if ($code === 'iw') {
            return self::JAPAN_CONTENT_LANG;
        }

        return $code;
    }

    /**
     * Тексты и оценки: Швейцария — ar, Япония — he.
     * Фото может браться с другого языка через resolveImageLanguage().
     */
    private function resolveContentLanguage(string $requestedCode, bool $isJapan): ?Language
    {
        $preferred = $isJapan ? self::JAPAN_CONTENT_LANG : self::SWISS_CONTENT_LANG;

        $forced = Language::query()->where('code', $preferred)->first();
        if ($forced) {
            return $forced;
        }

        if ($requestedCode !== '') {
            $lang = Language::query()->where('code', $requestedCode)->first();
            if ($lang) {
                return $lang;
            }
        }

        return Language::getDefault();
    }

    /**
     * Язык для фото: ar/he если запрошен, иначе тот же что контент.
     */
    private function resolveImageLanguage(string $requestedCode, ?Language $contentLanguage): ?Language
    {
        if (in_array($requestedCode, [self::JAPAN_CONTENT_LANG, self::SWISS_CONTENT_LANG], true)) {
            $lang = Language::query()->where('code', $requestedCode)->first();
            if ($lang) {
                return $lang;
            }
        }

        return $contentLanguage;
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return array<string, list<int>>
     */
    private function parseUserChoices(array $catalog): array
    {
        $choices = [];

        foreach ($catalog as $stepKey => $item) {
            if (! is_array($item)) {
                continue;
            }

            $stepNum = null;
            if (preg_match('/^step_(\d+)$/', (string) $stepKey, $m)) {
                $stepNum = (int) $m[1];
            }

            if (! $stepNum) {
                continue;
            }

            $slots = [];
            if (isset($item['slots']) && is_array($item['slots'])) {
                foreach ($item['slots'] as $slotRaw) {
                    if (is_numeric($slotRaw) && (int) $slotRaw > 0) {
                        $slots[] = (int) $slotRaw;
                    }
                }
            } elseif (isset($item['slot']) && is_numeric($item['slot']) && (int) $item['slot'] > 0) {
                $slots[] = (int) $item['slot'];
            } else {
                $parsed = $this->parseSlot((string) ($item['value'] ?? ''));
                if ($parsed !== null && $parsed > 0) {
                    $slots[] = $parsed;
                }
            }

            $slots = array_values(array_unique($slots));
            if ($slots !== []) {
                $choices['step_'.$stepNum] = $slots;
            }
        }

        return $choices;
    }

    /**
     * Оценки по выбранным критериям: при нескольких слотах — среднее.
     *
     * @param  array<string, array<string, int|null>>  $steps
     * @param  array<string, list<int>>  $userChoices
     * @return array<string, int>
     */
    private function criteriaScoresForChoices(array $steps, array $userChoices): array
    {
        $scores = [];

        foreach ($userChoices as $stepKey => $slots) {
            $stepScores = $steps[$stepKey] ?? [];
            $picked = [];

            foreach ((array) $slots as $slot) {
                $score = $stepScores[(string) $slot] ?? null;
                if ($score !== null) {
                    $picked[] = (int) $score;
                }
            }

            if ($picked !== []) {
                $scores[$stepKey] = (int) round(array_sum($picked) / count($picked));
            }
        }

        return $scores;
    }

    /**
     * Средняя оценка шагов 2–7 (уточнение после пейзажа).
     *
     * @param  array<string, int>  $criteriaScores
     */
    private function calculateRestScore(array $criteriaScores): int
    {
        $rest = [];
        foreach ($criteriaScores as $stepKey => $score) {
            if ($stepKey === 'step_1') {
                continue;
            }
            $rest[] = $score;
        }

        if ($rest === []) {
            return 0;
        }

        return (int) round(array_sum($rest) / count($rest));
    }

    /**
     * Итоговый балл для отображения: step1 ≈ 55%, остальное ≈ 45%.
     */
    private function calculateWeightedScore(int $step1Score, int $restScore): int
    {
        if ($step1Score <= 0 && $restScore <= 0) {
            return 0;
        }

        if ($restScore <= 0) {
            return $step1Score;
        }

        return (int) round($step1Score * 0.55 + $restScore * 0.45);
    }

    private function parseSlot(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/-(\d+)$/', $value, $m)) {
            $slot = (int) $m[1];

            return $slot > 0 ? $slot : null;
        }

        if (ctype_digit($value)) {
            $slot = (int) $value;

            return $slot > 0 ? $slot : null;
        }

        return null;
    }

    private function plainDescription(mixed $raw): string
    {
        $html = is_string($raw) ? $raw : '';
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Относительный путь картинки категории (/uploads/catalog/...).
     * Абсолютный URL из БД обрезаем до pathname — хост подставит фронт.
     */
    private function relativeImagePath(mixed $raw): string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            $path = parse_url($value, PHP_URL_PATH);
            $value = is_string($path) ? $path : '';
        }

        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return '/'.ltrim($value, '/');
    }

    private function safeHtmlDescription(mixed $raw): string
    {
        $html = is_string($raw) ? trim($raw) : '';
        if ($html === '') {
            return '';
        }

        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return strip_tags(
            $html,
            '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><span><div>'
        );
    }

    private function toScore(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (! is_numeric($raw)) {
            return null;
        }

        return (int) $raw;
    }
}
