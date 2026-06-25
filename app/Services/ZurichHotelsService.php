<?php

namespace App\Services;

use App\Support\ApartmentPriceLevel;
use App\Support\DataForSeoTitle;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Тестовый сервис для страницы "Тест -> Отели Цюриха".
 *
 * Важно: это именно тестовая витрина. Она не пишет данные в БД.
 * Каждый заход на страницу делает live-запрос в DataForSEO, получает отели
 * для Цюриха, фильтрует записи без цены и показывает результат в таблице.
 *
 * Рабочая версия для всех кантонов находится отдельно в SwissHotelsService:
 * там данные сохраняются в swiss_hotels.
 */
class ZurichHotelsService
{
    /**
     * DataForSEO location_code для Zurich, Switzerland в business_data/google.
     *
     * Не путать с другими location_code из SERP API: одинаковые города/названия
     * в разных endpoint-ах DataForSEO могут иметь разные коды.
     */
    public const LOCATION_CODE = 20151;

    /** Сколько строк показываем на одной странице тестовой таблицы. */
    private const PER_PAGE = 40;

    public function __construct(
        /** Общий клиент DataForSEO: хранит логин/пароль и проверяет ошибки API. */
        private readonly DataForSeoClient $client,
    ) {}

    public function paginate(Request $request): LengthAwarePaginator
    {
        // Сначала получаем полный список подходящих отелей из API.
        // Дальше режем массив вручную, потому что источник данных не БД, а live API.
        $items = $this->fetchFilteredItems();

        // Номер страницы берём из query string (?page=2).
        // max(1, ...) защищает от page=0 / отрицательных значений.
        $page = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * self::PER_PAGE;

        // LengthAwarePaginator нужен, чтобы Blade мог вывести стандартные ссылки пагинации.
        return new LengthAwarePaginator(
            array_slice($items, $offset, self::PER_PAGE),
            count($items),
            self::PER_PAGE,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    /**
     * @return list<array{title: string, level: int, stars: ?int, price: float}>
     */
    private function fetchFilteredItems(): array
    {
        // Без DATAFORSEO_LOGIN / DATAFORSEO_PASSWORD запрос всё равно упадёт,
        // поэтому показываем понятную ошибку сразу.
        if (! $this->client->credentialsConfigured()) {
            throw new \RuntimeException('DATAFORSEO_LOGIN или DATAFORSEO_PASSWORD не заданы в .env');
        }

        // Запрос в DataForSEO Hotel Searches:
        // - location_code: только Цюрих;
        // - keyword=hotels: обычный поиск отелей;
        // - currency=USD: цена приходит в долларах, как в текущей таблице;
        // - depth=140: просим больше результатов, чем нужно на одну страницу,
        //   чтобы после фильтрации/дедупликации осталось достаточно строк.
        $response = $this->client->post(DataForSeoClient::HOTEL_SEARCHES_URL, [[
            'location_code' => self::LOCATION_CODE,
            'keyword' => 'hotels',
            'currency' => 'USD',
            'language_code' => 'en',
            'depth' => 140,
        ]]);

        // Структура ответа DataForSEO вложенная:
        // tasks[0].result[0].items — список найденных отелей.
        // Если API вернул неожиданный ответ, считаем список пустым.
        $rawItems = $response['tasks'][0]['result'][0]['items'] ?? [];

        // Приводим сырые элементы API к нашему минимальному формату:
        // title, stars, price.
        //
        // mapItem() вернёт null, если:
        // - нет нормального названия;
        // - нет цены;
        // - цена <= 0.
        //
        // filter() после mapItem() убирает эти null.
        $items = Collection::make($rawItems)
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item) => $this->mapItem($item))
            ->filter()
            ->values()
            ->all();

        // DataForSEO может вернуть один и тот же отель несколько раз.
        // Для бюджета нам достаточно одной записи с минимальной найденной ценой.
        $items = $this->dedupeByTitle($items);

        $mapped = [];

        // ApartmentPriceLevel::assign() делит цены внутри текущего списка на 3 уровня:
        // 1 — дешёвые, 2 — средние, 3 — дорогие.
        //
        // Здесь "level" НЕ равен звёздам отеля.
        // Звёзды сохраняются отдельно в поле stars.
        foreach (ApartmentPriceLevel::assign($items, 'price') as $item) {
            $mapped[] = [
                'title' => $item['title'],
                'level' => (int) $item['level'],
                'stars' => $item['stars'] ?? null,
                'price' => (float) $item['price'],
            ];
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{title: string, stars: ?int, price: float}|null
     */
    private function mapItem(array $item): ?array
    {
        // DataForSeoTitle::normalize():
        // - убирает пустые/битые названия;
        // - приводит строку к нормальному виду;
        // - обрезает слишком длинные значения под лимит VARCHAR(255).
        $title = DataForSeoTitle::normalize($item['title'] ?? null);

        // stars — опциональное поле от DataForSEO.
        // Некоторые отели приходят без звёзд, это нормально.
        $stars = $item['stars'] ?? null;

        // Цена в Hotel Searches лежит внутри prices.price.
        // Если prices не массив, считаем, что цены нет.
        $prices = is_array($item['prices'] ?? null) ? $item['prices'] : [];
        $price = $prices['price'] ?? null;

        // Без названия строку нельзя показать и нельзя корректно дедуплицировать.
        if ($title === null) {
            return null;
        }

        // Для бюджетного расчёта нужны только отели с реальной положительной ценой.
        if ($price === null || (float) $price <= 0.0) {
            return null;
        }

        // Звёзды приводим к int только если значение положительное.
        // Иначе оставляем null, чтобы UI показал "—".
        $starsInt = ($stars !== null && (int) $stars > 0) ? (int) $stars : null;

        return [
            'title' => $title,
            'stars' => $starsInt,
            'price' => (float) $price,
        ];
    }

    /**
     * @param  list<array{title: string, stars: ?int, price: float}>  $items
     * @return list<array{title: string, stars: ?int, price: float}>
     */
    private function dedupeByTitle(array $items): array
    {
        $unique = [];

        foreach ($items as $item) {
            // Ключ делаем регистронезависимым:
            // "Hotel ABC" и "hotel abc" считаются одним отелем.
            $key = mb_strtolower($item['title']);

            // Если отель встретился впервые — сохраняем.
            // Если уже был — оставляем вариант с меньшей ценой.
            if (! isset($unique[$key]) || $item['price'] < $unique[$key]['price']) {
                $unique[$key] = $item;
            }
        }

        return array_values($unique);
    }
}
